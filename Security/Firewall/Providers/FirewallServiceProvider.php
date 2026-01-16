<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Service provider for the firewall system.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Providers;

use Core\Ioc\ContainerInterface;
use Core\Services\ConfigServiceInterface;
use Core\Services\ServiceProvider;
use Security\Firewall\Middleware\FirewallMiddleware;

class FirewallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(FirewallMiddleware::class, function (ContainerInterface $container) {
            $config = $container->get(ConfigServiceInterface::class);
            $namespaces = $config->get('firewall.drivers');
            $drivers = [];
            foreach ($namespaces as $namespace) {
                $drivers[] = $container->get($namespace);
            }

            return new FirewallMiddleware($drivers);
        });
    }
}
