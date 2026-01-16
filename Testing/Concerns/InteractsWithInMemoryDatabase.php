<?php

declare(strict_types=1);

namespace Testing\Concerns;

use Core\Ioc\Container;
use Database\BaseModel;
use Database\ConnectionInterface;
use Database\DB;
use Database\Migration\Migrator;
use Helpers\File\Paths;
use Testing\Support\DatabaseTestHelper;

trait InteractsWithInMemoryDatabase
{
    protected function setupInMemoryDatabase(): void
    {
        $connection = DatabaseTestHelper::setupInMemoryDatabase();
        DB::setDefaultConnection($connection);
        BaseModel::setConnection($connection);

        // Bind to container for strict dependency injection
        Container::getInstance()->instance(ConnectionInterface::class, $connection);
    }

    protected function teardownInMemoryDatabase(): void
    {
        DatabaseTestHelper::resetDefaultConnection();
    }

    public function runAppMigrations(): void
    {
        $paths = [Paths::basePath('App/storage/database/migrations')];
        $testPath = Paths::basePath('tests/System/Migrations');

        if (is_dir($testPath)) {
            $paths[] = $testPath;
        }

        $connection = resolve(ConnectionInterface::class);
        $connection->dropAllTables();

        $migrator = new Migrator($connection, $paths);
        $migrator->run();
    }
}
