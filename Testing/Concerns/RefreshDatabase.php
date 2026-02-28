<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait RefreshDatabase implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Concerns;

use Database\BaseModel;
use Database\ConnectionInterface;
use Database\Helpers\DatabaseOperationConfig;
use Database\Migration\Migrator;
use Database\NullConnection;
use Helpers\File\Paths;
use Throwable;

trait RefreshDatabase
{
    protected static bool $migrated = false;

    public function refreshDatabase(): void
    {
        if (isset($this->refreshDatabase) && $this->refreshDatabase === false) {
            return;
        }

        $connection = resolve(ConnectionInterface::class);

        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        // Reset all auto-increment sequences for SQLite to ensure IDs always start from 1
        if (str_contains(strtolower($connection->getDriver()), 'sqlite')) {
            try {
                $connection->statement("DELETE FROM sqlite_sequence");
            } catch (Throwable $e) {
                // sqlite_sequence might not exist if no autoincrement tables were ever created
            }
        }

        $this->runDatabaseMigrations();
        $this->beginDatabaseTransaction();

        if (method_exists(BaseModel::class, 'clearBootedState')) {
            BaseModel::clearBootedState();
        }
    }

    protected function runDatabaseMigrations(): void
    {
        if (static::$migrated) {
            return;
        }

        $connection = resolve(ConnectionInterface::class);

        if ($connection instanceof NullConnection) {
            return;
        }

        $config = resolve(DatabaseOperationConfig::class);
        $paths = [$config->getMigrationsPath()];

        $testMigrationPath = Paths::basePath('tests/System/Migrations');

        if (is_dir($testMigrationPath)) {
            $paths[] = $testMigrationPath;
        }

        $fixtureMigrationPath = Paths::basePath('System/Testing/Fixtures/Migrations');
        if (is_dir($fixtureMigrationPath)) {
            $paths[] = $fixtureMigrationPath;
        }

        $connection->dropAllTables();

        $migrator = new Migrator($connection, $paths);
        $migrator->run();

        static::$migrated = true;
    }

    protected function beginDatabaseTransaction(): void
    {
        $connection = resolve(ConnectionInterface::class);

        if ($connection instanceof NullConnection) {
            return;
        }

        $connection->beginTransaction();

        $this->beforeApplicationDestroyed(function () use ($connection) {
            $connection->rollBack();
        });
    }
}
