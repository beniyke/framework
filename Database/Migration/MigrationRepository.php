<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Repository for managing migration history and batches.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Migration;

use Database\ConnectionInterface;
use Database\Schema\Schema;

class MigrationRepository
{
    protected ConnectionInterface $connection;

    protected string $table = 'migrations';

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        Schema::setConnection($connection);
        Schema::createMigrationsTable();
    }

    public function getRan(): array
    {
        $sql = "SELECT migration FROM {$this->table} ORDER BY batch ASC, migration ASC";
        $results = $this->connection->select($sql);

        return array_column($results, 'migration');
    }

    public function getLastBatch(): array
    {
        $maxBatch = $this->getCurrentBatchNumber();
        if ($maxBatch === 0) {
            return [];
        }

        $sql = "SELECT migration, batch FROM {$this->table} WHERE batch = ? ORDER BY migration DESC";

        return $this->connection->select($sql, [$maxBatch]);
    }

    public function getMigrationsForSteps(int $steps): array
    {
        if ($steps <= 0) {
            return [];
        }

        $sql = "SELECT migration, batch FROM {$this->table} ORDER BY batch DESC, migration DESC LIMIT {$steps}";

        return $this->connection->select($sql);
    }

    public function getMigratedFiles(): array
    {
        if (! Schema::hasTable($this->table)) {
            return [];
        }

        $sql = "SELECT migration, batch FROM {$this->table} ORDER BY batch DESC, migration DESC";

        return $this->connection->select($sql);
    }

    public function log(string $file, int $batch): void
    {
        $sql = "INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)";
        $this->connection->statement($sql, [$file, $batch]);
    }

    public function delete(string $file): void
    {
        $sql = "DELETE FROM {$this->table} WHERE migration = ?";
        $this->connection->statement($sql, [$file]);
    }

    public function getNextBatchNumber(): int
    {
        return $this->getCurrentBatchNumber() + 1;
    }

    public function getCurrentBatchNumber(): int
    {
        $sql = "SELECT MAX(batch) as max_batch FROM {$this->table}";
        $result = $this->connection->selectOne($sql);

        return (int) ($result['max_batch'] ?? 0);
    }

    public function dropTable(): void
    {
        Schema::dropIfExists($this->table);
    }
}
