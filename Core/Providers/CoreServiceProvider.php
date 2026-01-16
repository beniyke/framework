<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Core Service Provider for registering fundamental application services.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Providers;

use Core\Console;
use Core\Ioc\ContainerInterface;
use Core\Services\CliService;
use Core\Services\CliServiceInterface;
use Core\Services\ConfigService;
use Core\Services\ConfigServiceInterface;
use Core\Services\ServiceProvider;
use Core\Support\Adapters\Interfaces\EnvironmentInterface;
use Core\Support\Adapters\Interfaces\SapiInterface;
use Core\Views\ViewEngine;
use Core\Views\ViewInterface;
use Helpers\DateTimeHelper;
use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Helpers\File\Contracts\LoggerInterface;
use Helpers\File\FileLogger;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(LoggerInterface::class, FileLogger::class);
        $this->container->singleton(ViewInterface::class, ViewEngine::class);
        $this->container->singleton(DateTimeHelper::class);

        $this->container->singleton(ConfigServiceInterface::class, function (ContainerInterface $container) {
            return new ConfigService(
                $container->get(EnvironmentInterface::class),
                $container->get(PathResolverInterface::class),
                $container->get(FileMetaInterface::class),
                $container->get(FileReadWriteInterface::class),
                $container->get(FileManipulationInterface::class)
            );
        });

        $this->container->singleton(Console::class, function (ContainerInterface $container) {
            return new Console(
                $container->get(PathResolverInterface::class),
                $container->get(FileMetaInterface::class),
                $container->get(FileReadWriteInterface::class),
                $container->get(SapiInterface::class),
                $container
            );
        });

        $this->container->singleton(CliServiceInterface::class, function () {
            $cliService = new CliService();
            $cliService->setArguments($_SERVER['argv'] ?? []);

            return $cliService;
        });
    }
}
