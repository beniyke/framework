<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Facilitates fluent route registration.
 *
 * @author BenIyke <beniyke34@gmail.com>
 */

namespace Core\Route;

use Closure;

class RouteGroup
{
    private array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function prefix(string $prefix): self
    {
        $this->attributes['prefix'] = $prefix;

        return $this;
    }

    public function middleware(string|array|Closure $middleware): self
    {
        $this->attributes['middleware'] = $middleware;

        return $this;
    }

    /**
     * Set the group to exclusive (bypass global middleware).
     */
    public function exclusive(): self
    {
        $this->attributes['exclusive'] = true;

        return $this;
    }

    public function as(string $name): self
    {
        $this->attributes['as'] = $name;

        return $this;
    }

    /**
     * Register a GET route within this group.
     */
    public function get(string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        return $this->register('GET', $path, $action, $method);
    }

    /**
     * Register a POST route within this group.
     */
    public function post(string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        return $this->register('POST', $path, $action, $method);
    }

    /**
     * Register a PUT route within this group.
     */
    public function put(string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        return $this->register('PUT', $path, $action, $method);
    }

    /**
     * Register a PATCH route within this group.
     */
    public function patch(string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        return $this->register('PATCH', $path, $action, $method);
    }

    /**
     * Register a DELETE route within this group.
     */
    public function delete(string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        return $this->register('DELETE', $path, $action, $method);
    }

    /**
     * Register a route for all common HTTP methods within this group.
     */
    public function any(string $path, string|array|Closure $action): array
    {
        $instances = [];
        Route::group($this->attributes, function () use ($path, $action, &$instances) {
            $instances = Route::any($path, $action);
        });

        return $instances;
    }

    /**
     * Register a route for specific HTTP methods within this group.
     */
    public function match(array $methods, string $path, string|array|Closure $action): array
    {
        $instances = [];
        Route::group($this->attributes, function () use ($methods, $path, $action, &$instances) {
            $instances = Route::match($methods, $path, $action);
        });

        return $instances;
    }

    /**
     * Register a redirect route within this group.
     */
    public function redirect(string $path, string $destination, int $status = 302): ?RouteInstance
    {
        $instance = null;
        Route::group($this->attributes, function () use ($path, $destination, $status, &$instance) {
            $instance = Route::redirect($path, $destination, $status);
        });

        return $instance;
    }

    /**
     * Register a view route within this group.
     */
    public function view(string $path, string $view, array $data = []): ?RouteInstance
    {
        $instance = null;
        Route::group($this->attributes, function () use ($path, $view, $data, &$instance) {
            $instance = Route::view($path, $view, $data);
        });

        return $instance;
    }

    /**
     * Register a resource route within this group.
     */
    public function resource(string $name, string $controller): void
    {
        Route::group($this->attributes, function () use ($name, $controller) {
            Route::resource($name, $controller);
        });
    }

    /**
     * Register a route group within this group.
     */
    public function group(array|Closure $attributes, ?Closure $callback = null): void
    {
        if ($attributes instanceof Closure) {
            $callback = $attributes;
            $attributes = [];
        }

        $mergedAttributes = array_merge_recursive($this->attributes, $attributes);
        Route::group($mergedAttributes, $callback);
    }

    /**
     * Internal helper to register a route with current attributes.
     */
    private function register(string $httpMethod, string $path, string|array|Closure $action, ?string $method = null): ?RouteInstance
    {
        $instance = null;
        Route::group($this->attributes, function () use ($httpMethod, $path, $action, $method, &$instance) {
            $instance = Route::{strtolower($httpMethod)}($path, $action, $method);
        });

        return $instance;
    }
}
