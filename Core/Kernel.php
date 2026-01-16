<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The Kernel serves as the central processing unit for the application web layer.
 * It orchestrates the loading of service providers, error handling, and the
 * initialization of the dependency injection container.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core;

use Core\Error\ConfigurationException;
use Core\Error\ErrorHandler;
use Core\Ioc\ContainerInterface;
use Core\Services\ConfigServiceInterface;
use Database\Providers\DatabaseServiceProvider;
use Debugger\Debugger;
use Debugger\Providers\DebuggerServiceProvider;
use Defer\Providers\DeferServiceProvider;
use Helpers\Encryption\EncryptionServiceProvider;
use Helpers\Http\Request;
use Helpers\Http\Response;
use Mail\Providers\MailServiceProvider;
use Security\Firewall\Providers\FirewallServiceProvider;

class Kernel
{
    private readonly ContainerInterface $container;

    private readonly string $appBasePath;

    private array $providers = [
        FirewallServiceProvider::class,
        MailServiceProvider::class,
        DatabaseServiceProvider::class,
        DebuggerServiceProvider::class,
        EncryptionServiceProvider::class,
        DeferServiceProvider::class,
    ];

    public function __construct(ContainerInterface $container, string $appBasePath)
    {
        $this->container = $container;
        $this->appBasePath = $appBasePath;

        // Bind the kernel instance to the container
        $this->container->instance(self::class, $this);

        $this->initializeContainerBindings();
    }

    public function boot(): void
    {
        $bootstrapper = $this->container->get(Bootstrapper::class);
        $bootstrapper->run();

        $this->registerErrorHandler();
        $this->registerServiceProviders();
        $this->setApplicationTimezone();
    }

    private function initializeContainerBindings(): void
    {
        $this->container->singleton(ContainerInterface::class, fn () => $this->container);
        $this->container->singleton(ProviderManager::class, function (ContainerInterface $container) {
            $manager = new ProviderManager($container);
            $config = $container->get(ConfigServiceInterface::class);
            $providers = array_merge($this->providers, $config->get('providers') ?? []);
            $manager->setProviders($providers);

            return $manager;
        });

        $this->container->singleton(Bootstrapper::class, function (ContainerInterface $container) {
            return new Bootstrapper($container, $this->appBasePath);
        });
    }

    private function registerErrorHandler(): void
    {
        $errorHandler = $this->container->make(ErrorHandler::class);
        $errorHandler->register();
    }

    private function registerServiceProviders(): void
    {
        $debugger = null;

        if ($this->isDebugEnabled() && class_exists(Debugger::class)) {
            $debugger = Debugger::getInstance($this->container);
            if ($debugger->getDebugBar()->hasCollector('timeline')) {
                $debugger->getDebugBar()['timeline']->startMeasure('boot', 'Booting Application');
            }
        }

        $provider = $this->container->get(ProviderManager::class);
        $provider->boot();

        if ($debugger !== null && $debugger->getDebugBar()->hasCollector('timeline')) {
            $debugger->getDebugBar()['timeline']->stopMeasure('boot');
        }
    }

    private function isDebugEnabled(): bool
    {
        $config = $this->container->get(ConfigServiceInterface::class);

        return (bool) $config->get('debug', false);
    }

    private function setApplicationTimezone(): void
    {
        $config = $this->container->get(ConfigServiceInterface::class);

        if ($timezone = $config->get('timezone')) {
            date_default_timezone_set($timezone);
        } else {
            throw new ConfigurationException(
                'Timezone configuration is missing. Please set "timezone" in your config file (e.g., "UTC", "America/New_York").'
            );
        }
    }

    public function handle(Request $request): Response
    {
        $app = $this->container->get(App::class);

        return $app->handle($request);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
