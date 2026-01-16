<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Handles atomic Read-Modify-Write operations for firewall counters.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Persistence;

use Database\ConnectionInterface;
use Helpers\DateTimeHelper;

class CounterStore
{
    private const TABLE_NAME = 'firewall_counter';

    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function atomicUpdate(string $key, int $duration): array
    {
        $tableName = self::TABLE_NAME;

        $updatedData = $this->db->transaction(function (ConnectionInterface $conn) use ($key, $duration, $tableName) {
            $select_lock_sql = "SELECT request_count, start_time FROM {$tableName} WHERE key_hash = ?";

            if (! $conn->isSqlite()) {
                $select_lock_sql .= ' FOR UPDATE';
            }

            $row = $conn->selectOne($select_lock_sql, [$key]);
            $now = DateTimeHelper::now();
            $count = 0;
            $startTime = $now->toDateTimeString();

            if ($row) {
                $rowStartTime = DateTimeHelper::parse($row['start_time']);
                $elapsed = $rowStartTime->diffInSeconds($now);

                if ($elapsed >= $duration) {
                    $count = 1;
                } else {
                    $count = (int) $row['request_count'] + 1;
                    $startTime = $row['start_time'];
                }

                $update_sql = "UPDATE {$tableName} SET request_count = ?, start_time = ? WHERE key_hash = ?";
                $conn->update($update_sql, [$count, $startTime, $key]);
            } else {
                $count = 1;
                $insert_sql = "INSERT INTO {$tableName} (key_hash, request_count, start_time) VALUES (?, ?, ?)";
                $conn->statement($insert_sql, [$key, $count, $startTime]);
            }

            return ['request' => $count, 'datetime' => $startTime];
        });

        return $updatedData;
    }

    public function get(string $key): ?array
    {
        $tableName = self::TABLE_NAME;
        $select_sql = "SELECT request_count, start_time FROM {$tableName} WHERE key_hash = ?";
        $row = $this->db->selectOne($select_sql, [$key]);

        if (! $row) {
            return null;
        }

        return [
            'request' => (int) $row['request_count'],
            'datetime' => $row['start_time'],
        ];
    }

    public function clear(string $key): void
    {
        $delete_sql = 'DELETE FROM ' . self::TABLE_NAME . ' WHERE key_hash = ?';
        $this->db->delete($delete_sql, [$key]);
    }
}
