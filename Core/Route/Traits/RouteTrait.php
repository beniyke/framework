<?php

declare(strict_types=1);

/**
 * Provides routing functionality for web applications, including methods for checking route existence, resolving middleware routes, handling wildcards, and stripping common prefixes from route strings.
 *
 * @author BenIyke <beniyke34@gmail.com> | (twitter:@BigBeniyke)
 */

namespace Core\Route\Traits;

trait RouteTrait
{
    protected function routeExist(array $routes, string $current_route): bool
    {
        $unpacked_routes = [];

        foreach ($routes as $route_set) {
            if (is_array($route_set)) {
                foreach ($route_set as $route_string) {
                    $unpacked_routes = array_merge($unpacked_routes, static::unpackCollection($route_string));
                }
            } else {
                $unpacked_routes = array_merge($unpacked_routes, static::unpackCollection($route_set));
            }
        }

        if (in_array($current_route, $unpacked_routes)) {
            return true;
        }

        return $this->resolveWildcard($unpacked_routes, $current_route);
    }

    public function stripCommonPrefix(?string $route1, ?string $route2): string
    {
        $route1 = $route1 ?? '';
        $route2 = $route2 ?? '';

        $route1_parts = explode('/', trim($route1, '/'));
        $route2_parts = explode('/', trim($route2, '/'));

        $common_length = 0;
        foreach ($route1_parts as $index => $part) {
            if (isset($route2_parts[$index]) && $part === $route2_parts[$index]) {
                $common_length++;
            } else {
                break;
            }
        }

        return implode('/', array_slice($route1_parts, $common_length));
    }

    protected function resolveMiddlewareRoutes(array $routes): array
    {
        $result = [];

        foreach ($routes as $middleware => $middleware_routes) {
            foreach ($middleware_routes as $route) {
                $sorted = static::unpackCollection($route);

                foreach ($sorted as $string) {
                    $result[$middleware][] = $string;
                }
            }
        }

        return $result;
    }

    protected function getMiddlewareStack(): ?array
    {
        $resolved_routes = $this->resolveMiddlewareRoutes($this->config('route.auth') ?? []);
        $current_route = $this->route();

        $target_middleware = null;

        foreach ($resolved_routes as $middleware => $routes) {
            if ($this->routeExist($routes, $current_route)) {
                $target_middleware = $middleware;
                break;
            }
        }

        if ($target_middleware) {
            return $this->config("middleware.$target_middleware") ?? null;
        }

        return null;
    }

    public function routeIsApi(): bool
    {
        return $this->routeExist($this->config('route.api') ?? [], $this->route());
    }

    public function routeShouldBypassAuth(): bool
    {
        $resolved_routes = $this->resolveMiddlewareRoutes($this->config('route.auth') ?? []);
        $current_route = $this->route();

        $middleware_name = null;
        foreach ($resolved_routes as $middleware => $routes) {
            if ($this->routeExist($routes, $current_route)) {
                $middleware_name = $middleware;
                break;
            }
        }

        if (! $middleware_name) {
            return false;
        }

        $exclusions = $this->config("route.auth-exclude.$middleware_name") ?? [];

        return $this->routeExist($exclusions, $current_route);
    }

    public function isLoginRoute(): bool
    {
        return in_array($this->route(), $this->config('route.login') ?? []);
    }

    public function isLogoutRoute(): bool
    {
        return in_array($this->route(), $this->config('route.logout') ?? []);
    }

    public function shouldRequireAuth(): bool
    {
        return ! $this->routeShouldBypassAuth();
    }

    public function refererRoute(): ?string
    {
        $url = $this->referer();

        if (! $url) {
            return null;
        }

        $route = str_replace($this->baseUrl(), '', $url);

        return $route ?: null;
    }

    /**
     * Handles wildcard route matching (e.g., 'prefix/*').
     */
    private function resolveWildcard(array $routes, string $current_route): bool
    {
        $split_current = static::split($current_route);

        foreach ($routes as $route) {
            $split_route = static::split($route);

            if ($split_route['first'] === $split_current['first'] && $split_route['second'] === '*') {
                return true;
            }
        }

        return false;
    }

    /**
     * Unpacks a route string containing curly brace collection syntax
     * (e.g., 'prefix/{route1, route2}') into an array of full routes.
     */
    private static function unpackCollection(string $route): array
    {
        $parts = preg_split('/\{/', $route, 2);

        if (empty($parts[1])) {
            return [$route];
        }

        preg_match_all('/(?:\s*)([^,}]+)(?:\s*)(?:,|})/', $parts[1], $matches);

        return array_map(fn ($match) => $parts[0].trim($match), $matches[1]);
    }

    private static function split(string $string): array
    {
        $split = explode('/', $string, 2);

        return ['first' => $split[0], 'second' => $split[1] ?? null];
    }

    public function routeName(string $route): ?string
    {
        return $this->config("route.names.{$route}");
    }
}
