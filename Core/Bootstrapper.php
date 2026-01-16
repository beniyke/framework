<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Bootstrapper is responsible for initializing the application ecosystem.
 * It registers system providers, loads core helpers, environment configurations,
 * and boots the service providers to prepare the application for handling requests.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core;

use Core\Ioc\ContainerInterface;
use Core\Providers\CoreServiceProvider;
use Core\Providers\HttpServiceProvider;
use Core\Providers\SystemAdapterProvider;
use Core\Services\DotenvInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Helpers\File\Paths;

class Bootstrapper
{
    private array $providers = [
        SystemAdapterProvider::class,
        CoreServiceProvider::class,
        HttpServiceProvider::class,
    ];

    private readonly ContainerInterface $container;

    private array $loadedProviders = [];

    public function __construct(ContainerInterface $container, string $appBasePath)
    {
        $this->container = $container;
        Paths::setBasePath($appBasePath);
    }

    public function run(): void
    {
        $systemProviderClass = array_shift($this->providers);
        $systemProvider = new $systemProviderClass($this->container);
        $systemProvider->register();
        $this->loadedProviders[] = $systemProvider;

        $paths = $this->container->get(PathResolverInterface::class);

        $this->loadComposerAutoload($paths);
        $this->loadPackageAutoloaders($paths);
        $this->loadApplicationHelpers($paths);
        $this->container->get(DotenvInterface::class)->load();
        $this->loadConfigFunctions($paths);
        $this->registerRemainingProviders();
        $this->bootServiceProviders();
    }

    private function registerRemainingProviders(): void
    {
        foreach ($this->providers as $providerClass) {
            $provider = new $providerClass($this->container);
            $provider->register();
            $this->loadedProviders[] = $provider;
        }
    }

    private function bootServiceProviders(): void
    {
        foreach ($this->loadedProviders as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
    }

    private function loadComposerAutoload(PathResolverInterface $paths): void
    {
        $vendorPath = $paths->basePath('vendor' . DIRECTORY_SEPARATOR . 'autoload.php');
        if (is_file($vendorPath)) {
            require_once $vendorPath;
        }
    }

    private function loadConfigFunctions(PathResolverInterface $paths): void
    {
        $functionsPath = $paths->appPath('Config' . DIRECTORY_SEPARATOR . 'functions.php');
        if (is_file($functionsPath)) {
            require_once $functionsPath;
        }
    }

    private function loadApplicationHelpers(PathResolverInterface $paths): void
    {
        $basePath = $paths->corePath('Globals');
        $specificFiles = ['env', 'debug', 'array', 'execution', 'files', 'format', 'http', 'misc', 'security', 'string', 'validation', 'view'];

        if (is_dir($basePath)) {
            foreach ($specificFiles as $file) {
                $path = $basePath . DIRECTORY_SEPARATOR . $file . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        }
    }

    private function loadPackageAutoloaders(PathResolverInterface $paths): void
    {
        $packageAutoloadPath = $paths->basePath('packages' . DIRECTORY_SEPARATOR . 'autoload.php');

        if (is_file($packageAutoloadPath)) {
            $files = include $packageAutoloadPath;

            if (is_array($files)) {
                foreach ($files as $path) {
                    $packageFile = $paths->basePath('packages' . DIRECTORY_SEPARATOR . $path . '.php');
                    if (is_file($packageFile)) {
                        require_once $packageFile;
                    }
                }
            }
        }
    }
}
