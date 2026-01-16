<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Abstract base class for all service providers.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Services;

use Core\Ioc\ContainerInterface;

abstract class ServiceProvider implements ServiceProviderInterface
{
    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    abstract public function register(): void;

    /**
     * Bootstrap any application services. This method is called after all
     * other service providers have been registered.
     */
    public function boot(): void
    {
    }
}
