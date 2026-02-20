<?php

declare(strict_types=1);

namespace Helpers\File\Storage\Providers;

use Core\Services\ConfigServiceInterface;
use Core\Services\ServiceProvider;
use Helpers\File\Storage\StorageInterface;
use Helpers\File\Storage\StorageManager;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->container->singleton(StorageManager::class, function ($container) {
            return new StorageManager($container->get(ConfigServiceInterface::class));
        });

        $this->container->singleton(StorageInterface::class, function ($container) {
            return $container->get(StorageManager::class)->disk();
        });
    }
}
