<?php

declare(strict_types=1);

namespace Testing\Support;

use App\Models\User;
use App\Services\Auth\Interfaces\AuthServiceInterface;
use Core\Ioc\Container;
use Database\BaseModel;
use Database\Connection;
use Database\ConnectionInterface;
use Database\Schema\Schema;
use Error;
use Exception;
use Helpers\File\Paths;
use Mockery\MockInterface;
use RuntimeException;
use Testing\Mocks\AuthMock;

class DatabaseTestHelper
{
    protected static ?ConnectionInterface $connection = null;

    public static function setupInMemoryDatabase(): ConnectionInterface
    {
        // If we already have a connection, clean it up before starting fresh
        if (self::$connection !== null) {
            self::resetDefaultConnection();
        }

        try {
            $connection = Connection::configure('sqlite::memory:');
            $connection->connect();

            self::$connection = $connection;

            // Sync other components with this connection
            Schema::setConnection($connection);
            BaseModel::setConnection($connection);

            // Also update the DB facade's default connection if possible
            if (class_exists('Database\\DB')) {
                \Database\DB::setDefaultConnection($connection);
            }
        } catch (Exception $e) {
            throw new RuntimeException("Failed to connect to in-memory database: " . $e->getMessage());
        }

        return self::$connection;
    }

    public static function resetDefaultConnection(): void
    {
        if (self::$connection) {
            // Use the connection's own robust dropAllTables method
            self::$connection->dropAllTables();
            self::$connection->disconnect();
        }
        self::$connection = null;
    }

    public static function dropAllTables(): void
    {
        if (self::$connection) {
            self::$connection->dropAllTables();
        }
    }

    public static function cleanupTables(array $tables): void
    {
        if (self::$connection) {
            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }
        }
    }

    public static function runPackageMigrations(string $packageName, ?string $basePath = null): void
    {
        $migrationPath = $basePath ?? Paths::basePath("packages/{$packageName}/Database/Migrations");
        $migrationPath = Paths::normalize($migrationPath);

        if (!is_dir($migrationPath)) {
            return;
        }

        $files = glob($migrationPath . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            require_once $file;
            $filename = basename($file, '.php');

            $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);
            $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $className)));

            // Try potential namespaced or suffix variants
            $class = $className;
            if (!class_exists($class)) {
                $class = 'Database\\Migrations\\' . $className;
            }

            if (class_exists($class)) {
                $migration = new $class();
                if (method_exists($migration, 'up')) {
                    $migration->up();
                }
            }
        }
    }

    public static function runAppMigrations(?string $basePath = null): void
    {
        $migrationPath = $basePath ?? Paths::basePath('App/storage/database/migrations');
        $migrationPath = Paths::normalize($migrationPath);

        if (!is_dir($migrationPath)) {
            return;
        }

        $files = glob($migrationPath . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            require_once $file;
            $filename = basename($file, '.php');

            $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);
            $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $className)));

            // Try potential namespaced or suffix variants
            $class = $className;
            if (!class_exists($class)) {
                $class = 'Database\\Migrations\\' . $className;
            }

            if (class_exists($class)) {
                $migration = new $class();
                if (method_exists($migration, 'up')) {
                    $migration->up();
                }
            }
        }
    }

    public static function setupTestEnvironment(array $packages = [], bool $includeAppMigrations = false): ConnectionInterface
    {
        // Ensure Paths are initialized for the test environment
        self::initializePaths();

        // Bind AuthMock for packages that require it
        $container = container();
        if ($container instanceof Container && !($container instanceof MockInterface) && !$container->has(AuthServiceInterface::class)) {
            $container->singleton(AuthServiceInterface::class, AuthMock::class);
        }

        $connection = self::setupInMemoryDatabase();
        Schema::setConnection($connection);
        BaseModel::setConnection($connection);

        if ($includeAppMigrations) {
            self::runAppMigrations();
        }

        foreach ($packages as $package) {
            self::runPackageMigrations($package);
        }

        return $connection;
    }

    public static function createModelSchema(): void
    {
        self::runMigrationsFrom(Paths::basePath('System/Testing/Fixtures/Migrations'));
    }

    public static function createRelationSchema(): void
    {
        self::runMigrationsFrom(Paths::systemPath('Testing/Fixtures/Migrations'));
    }

    public static function runMigrationsFrom(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*.php');
        if ($files === false) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            require_once $file;
            $filename = basename($file, '.php');

            $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);
            $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $className)));

            // Try namespaced, Migration suffix, and global
            $namespaced = 'Testing\\Fixtures\\Migrations\\' . $className;
            $namespacedMigration = $namespaced . 'Migration';

            if (class_exists($namespacedMigration)) {
                $class = $namespacedMigration;
            } elseif (class_exists($namespaced)) {
                $class = $namespaced;
            } else {
                $class = $className;
            }

            if (class_exists($class)) {
                $migration = new $class();
                if (method_exists($migration, 'up')) {
                    $migration->up();
                }
            }
        }
    }

    public static function createMockUser(int $id = 1, array $attributes = []): User
    {
        $defaults = [
            'name' => 'Test User ' . $id,
            'email' => 'user' . $id . '_' . rand(1, 999) . '@example.com',
            'password' => 'password',
            'gender' => 'male',
            'refid' => 'USR' . $id . '_' . rand(1, 999),
        ];

        return User::create(array_merge($defaults, $attributes));
    }

    private static function initializePaths(): void
    {
        try {
            Paths::basePath();
        } catch (Error $e) {
            $basePath = rtrim(realpath('.') . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
            Paths::setBasePath($basePath);
        }
    }
}
