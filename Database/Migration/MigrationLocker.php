<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Manages database migration locking mechanisms.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Migration;

use Database\ConnectionInterface;
use PDOException;
use RuntimeException;

class MigrationLocker
{
    protected ConnectionInterface $connection;

    protected string $table = 'migration_locks';

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->ensureLockTableExists();
    }

    protected function ensureLockTableExists(): void
    {
        $driver = $this->connection->getDriver();

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (locked INTEGER PRIMARY KEY DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (`locked` INT(1) PRIMARY KEY DEFAULT 0, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP)";
        }

        $this->connection->statement($sql);
    }

    public function isLocked(): bool
    {
        try {
            $result = $this->connection->selectOne("SELECT locked FROM {$this->table} LIMIT 1");

            return (bool) ($result['locked'] ?? false);
        } catch (PDOException $e) {
            throw new RuntimeException('Could not check migration lock status: ' . $e->getMessage());
        }
    }

    public function acquireLock(): void
    {
        if ($this->isLocked()) {
            throw new RuntimeException('Migration already locked by another process.');
        }

        try {
            $driver = $this->connection->getDriver();

            if ($driver === 'sqlite') {
                $sql = "INSERT OR REPLACE INTO {$this->table} (locked, created_at) VALUES (1, CURRENT_TIMESTAMP)";
            } else {
                $sql = "INSERT INTO {$this->table} (locked) VALUES (1) ON DUPLICATE KEY UPDATE locked = 1, created_at = CURRENT_TIMESTAMP";
            }

            $this->connection->statement($sql);
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to acquire migration lock: ' . $e->getMessage());
        }
    }

    public function releaseLock(): void
    {
        try {
            $this->connection->statement("DELETE FROM {$this->table}");
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to release migration lock: ' . $e->getMessage());
        }
    }
}
