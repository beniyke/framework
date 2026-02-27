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

    public function __construct(ConfigServiceInterface $configService)
    {
        $this->config = $configService->get('middleware', []);
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
            return (array) $this->config[$middleware];
        }

        // Assume it's an FQCN or an alias already bound in container
        return [$middleware];
    }
}
