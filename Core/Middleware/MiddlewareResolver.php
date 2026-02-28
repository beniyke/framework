<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Resolves middleware aliases, groups, and closures.
 *
 * @author BenIyke <beniyke34@gmail.com>
 */

namespace Core\Middleware;

use Closure;
use Core\Services\ConfigServiceInterface;

class MiddlewareResolver
{
    private array $config;

    private array $systemGroups;

    public function __construct(ConfigServiceInterface $configService, array $systemGroups = [])
    {
        $this->config = $configService->get('middleware', []);
        $this->systemGroups = $systemGroups;
    }

    /**
     * Resolve a list of middleware into an expanded list of classes or closures.
     */
    public function resolve(array $middlewares): array
    {
        $resolved = [];

        foreach ($middlewares as $middleware) {
            $resolved = array_merge($resolved, $this->resolveSingle($middleware));
        }

        return $resolved;
    }

    /**
     * Resolve a single middleware name/closure.
     */
    private function resolveSingle(string|Closure $middleware): array
    {
        if ($middleware instanceof Closure) {
            return [$middleware];
        }

        // Check if it's a group (e.g., 'web', 'api')
        if (isset($this->config[$middleware])) {
            $systemMiddleware = $this->systemGroups[$middleware] ?? [];
            $userMiddleware = (array) $this->config[$middleware];

            return array_merge($systemMiddleware, $userMiddleware);
        }

        // Assume it's an FQCN or an alias already bound in container
        return [$middleware];
    }
}
