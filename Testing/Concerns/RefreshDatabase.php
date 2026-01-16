<?php

declare(strict_types=1);

namespace Testing\Concerns;

use Database\BaseModel;
use Database\ConnectionInterface;
use Database\Helpers\DatabaseOperationConfig;
use Database\Migration\Migrator;
use Database\NullConnection;
use Helpers\File\Paths;

trait RefreshDatabase
{
    public function refreshDatabase(): void
    {
        if (isset($this->refreshDatabase) && $this->refreshDatabase === false) {
            return;
        }

        $connection = resolve(ConnectionInterface::class);

        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        $this->runDatabaseMigrations();
        $this->beginDatabaseTransaction();

        if (method_exists(BaseModel::class, 'clearBootedState')) {
            BaseModel::clearBootedState();
        }
    }

    protected function runDatabaseMigrations(): void
    {
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

        $this->beforeApplicationDestroyed(function () use ($migrator) {
            $migrator->reset();
        });
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
