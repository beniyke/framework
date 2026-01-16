<?php

declare(strict_types=1);

namespace Testing\Concerns;

use Core\Services\ConfigServiceInterface;
use Core\Services\ServiceProvider;
use Database\ConnectionInterface;
use Database\Migration\Migrator;
use Helpers\File\Paths;
use RuntimeException;

trait InteractsWithPackages
{
    protected function bootPackage(string $packageName, ?string $providerClass = null, bool $runMigrations = false): void
    {
        $this->loadPackageConfig($packageName);

        if ($providerClass) {
            $this->registerPackageProvider($providerClass);
        } else {
            $guessedProvider = "{$packageName}\\Providers\\{$packageName}ServiceProvider";
            if (class_exists($guessedProvider)) {
                $this->registerPackageProvider($guessedProvider);
            }
        }

        if ($runMigrations) {
            $this->migratePackage($packageName);
        }
    }

    protected function loadPackageConfig(string $packageName): void
    {
        $configPath = Paths::basePath("packages/{$packageName}/Config/" . strtolower($packageName) . ".php");

        if (!file_exists($configPath)) {
            $configPath = Paths::basePath("packages/" . strtolower($packageName) . "/Config/" . strtolower($packageName) . ".php");
        }

        if (file_exists($configPath)) {
            $config = resolve(ConfigServiceInterface::class);
            $packageConfig = require $configPath;

            foreach ($packageConfig as $key => $value) {
                $config->set(strtolower($packageName) . ".{$key}", $value);
            }
        }
    }

    protected function migratePackage(string $packageName): void
    {
        $migrationPath = Paths::basePath("packages/{$packageName}/Database/Migrations");

        if (!is_dir($migrationPath)) {
            $migrationPath = Paths::basePath("packages/" . strtolower($packageName) . "/Database/Migrations");
        }

        if (is_dir($migrationPath)) {
            $connection = resolve(ConnectionInterface::class);
            $migrator = new Migrator($connection, $migrationPath);
            $migrator->run();
        }
    }

    protected function registerPackageProvider(string $providerClass): void
    {
        if (!class_exists($providerClass)) {
            throw new RuntimeException("Service provider [{$providerClass}] not found.");
        }

        $provider = new $providerClass(container());

        if (!$provider instanceof ServiceProvider) {
            throw new RuntimeException("Class [{$providerClass}] must extend Core\Services\ServiceProvider.");
        }

        $provider->register();
        $provider->boot();
    }

    protected function registerPackageMiddleware(string $name, string $middlewareClass, array $routes = []): void
    {
        $config = resolve(ConfigServiceInterface::class);
        $config->set("middleware.{$name}", [$middlewareClass]);

        if (!empty($routes)) {
            $existing = $config->get('route.auth', []);
            $existing[$name] = $routes;
            $config->set('route.auth', $existing);
        }
    }
}
