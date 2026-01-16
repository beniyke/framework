<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * System Adapter Provider for registering system-level adapters.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Providers;

use Core\Ioc\ContainerInterface;
use Core\Services\Dotenv;
use Core\Services\DotenvInterface;
use Core\Services\ServiceProvider;
use Core\Support\Adapters\EnvironmentAdapter;
use Core\Support\Adapters\Interfaces\EnvironmentInterface;
use Core\Support\Adapters\Interfaces\OSCheckerInterface;
use Core\Support\Adapters\Interfaces\SapiInterface;
use Core\Support\Adapters\OSCheckerAdapter;
use Core\Support\Adapters\SapiAdapter;
use Helpers\File\Adapters\FileManipulationAdapter;
use Helpers\File\Adapters\FileMetaAdapter;
use Helpers\File\Adapters\FileReadWriteAdapter;
use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Helpers\File\Adapters\PathResolverAdapter;

class SystemAdapterProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(PathResolverInterface::class, PathResolverAdapter::class);
        $this->container->singleton(SapiInterface::class, SapiAdapter::class);
        $this->container->singleton(EnvironmentInterface::class, EnvironmentAdapter::class);
        $this->container->singleton(OSCheckerInterface::class, OSCheckerAdapter::class);

        $this->container->singleton(FileManipulationInterface::class, FileManipulationAdapter::class);
        $this->container->singleton(FileMetaInterface::class, FileMetaAdapter::class);
        $this->container->singleton(FileReadWriteInterface::class, FileReadWriteAdapter::class);

        $this->container->singleton(DotenvInterface::class, function (ContainerInterface $container) {
            $paths = $container->get(PathResolverInterface::class);

            return new Dotenv(
                $paths->basePath(),
                $container->get(EnvironmentInterface::class),
                $paths,
                $container->get(FileMetaInterface::class),
                $container->get(FileReadWriteInterface::class),
                $container->get(FileManipulationInterface::class)
            );
        });
    }
}
