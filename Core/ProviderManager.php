<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * ProviderManager handles the registration and booting of service providers.
 * It manages both immediate and deferred providers to ensure efficient application startup.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core;

use Core\Ioc\ContainerInterface;
use Core\Services\DeferredServiceProvider;
use Core\Services\ServiceProviderInterface;
use RuntimeException;

class ProviderManager
{
    private array $providers = [];

    private bool $isBooted = false;

    private readonly ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function setProviders(array $providers): void
    {
        $this->providers = $providers;
    }

    public function boot(): void
    {
        if ($this->isBooted) {
            return;
        }

        if (empty($this->providers)) {
            throw new RuntimeException('Provider list is empty. Ensure the compiled configuration is loaded.');
        }

        $this->registerImmediateProviders();
        $this->registerDeferredProviders();

        $this->bootProviders();
        $this->isBooted = true;
    }

    private function registerImmediateProviders(): void
    {
        foreach ($this->providers as $provider_class) {
            if (! method_exists($provider_class, 'provides')) {
                $this->container->registerProvider($provider_class);
            }
        }
    }

    private function registerDeferredProviders(): void
    {
        foreach ($this->providers as $provider_class) {
            if (method_exists($provider_class, 'provides')) {
                if (! is_subclass_of($provider_class, DeferredServiceProvider::class)) {
                    throw new RuntimeException("Class {$provider_class} must implement DeferredServiceProvider.");
                }

                $provides = $provider_class::provides();
                $this->container->registerDeferredProvider($provider_class, $provides);
            }
        }
    }

    private function bootProviders(): void
    {
        foreach ($this->providers as $provider_class) {
            if (! is_subclass_of($provider_class, DeferredServiceProvider::class)) {
                $providerInstance = $this->container->get($provider_class);
                if ($providerInstance instanceof ServiceProviderInterface && method_exists($providerInstance, 'boot')) {
                    $providerInstance->boot();
                }
            }
        }
    }
}
