<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Middleware for the firewall system.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Middleware;

use Closure;
use Core\Middleware\MiddlewareInterface;
use Helpers\Http\Request;
use Helpers\Http\Response;

class FirewallMiddleware implements MiddlewareInterface
{
    private array $drivers;

    public function __construct(array $drivers)
    {
        $this->drivers = $drivers;
    }

    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        foreach ($this->drivers as $driver) {
            $driver->handle();
            if ($driver->isBlocked()) {
                $firewallResponse = $driver->getResponse();
                if ($response->isRedirect($firewallResponse['code'])) {
                    return $response->redirect($firewallResponse['content']);
                }

                return $response->status($firewallResponse['code'])
                    ->header($firewallResponse['header'])
                    ->body($firewallResponse['content']);
            }
        }

        return $next($request, $response);
    }
}
