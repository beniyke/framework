<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Manual Route registration and matching.
 *
 * @author BenIyke <beniyke34@gmail.com>
 */

namespace Core\Route;

use Closure;
use Core\Views\ViewInterface;
use Helpers\File\FileSystem;
use Helpers\File\Paths;

class Route
{
    /**
     * Stored static routes for O(1) lookup.
     */
    private static array $staticRoutes = [];

    /**
     * Dynamic routes for segment matching.
     */
    private static array $dynamicRoutes = [];

    /**
     * Optional fallback route.
     */
    private static ?RouteInstance $fallbackRoute = null;

    /**
     * Stack of active route groups.
     */
    private static array $groupStack = [];

    /**
     * Map of named routes.
     */
    private static array $names = [];

    /**
     * Controller namespace pattern.
     */
    private const CONTROLLER_NAMESPACE = 'App\\{module}\\Controllers\\{controller}Controller';

    public static function getControllerNamespace(): string
    {
        return self::CONTROLLER_NAMESPACE;
    }

    public static function get(string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        return self::add('GET', $path, $action, $method);
    }

    public static function post(string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        return self::add('POST', $path, $action, $method);
    }

    public static function put(string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        return self::add('PUT', $path, $action, $method);
    }

    public static function patch(string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        return self::add('PATCH', $path, $action, $method);
    }

    public static function delete(string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        return self::add('DELETE', $path, $action, $method);
    }

    /**
     * Register a route for all common HTTP methods.
     */
    public static function any(string $path, string|array|Closure $action): array
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $instances = [];
        foreach ($methods as $method) {
            $instances[] = self::add($method, $path, $action);
        }

        return $instances;
    }

    /**
     * Register a route for a specific set of HTTP methods.
     */
    public static function match(array $methods, string $path, string|array|Closure $action): array
    {
        $instances = [];
        foreach ($methods as $method) {
            $instances[] = self::add(strtoupper($method), $path, $action);
        }

        return $instances;
    }

    /**
     * Register a route that redirects to another URI.
     */
    public static function redirect(string $path, string $destination, int $status = 302): RouteInstance
    {
        return self::get($path, function () use ($destination, $status) {
            $url = (str_starts_with($destination, '/') || str_contains($destination, '://'))
                ? $destination
                : '/' . ltrim($destination, '/');

            return response()->redirect($url, $status);
        });
    }

    /**
     * Register a route that directly renders a view.
     */
    public static function view(string $path, string $view, array $data = []): RouteInstance
    {
        return self::get($path, function () use ($view, $data) {
            $engine = resolve(ViewInterface::class);

            if (str_contains($view, '::')) {
                [$module, $template] = explode('::', $view, 2);
                $engine->path(Paths::templatePath(null, $module));
                $view = $template;
            } elseif (! FileSystem::isAbsolute($view)) {
                // If not absolute and no module specified, use default templates path
                $engine->path(Paths::templatePath());
            }

            return $engine->template($view)
                ->data($data)
                ->render();
        });
    }

    /**
     * Register a standard RESTful resource route.
     */
    public static function resource(string $name, string $controller): void
    {
        self::get($name, [$controller, 'index'])->name($name . '.index');
        self::get($name . '/create', [$controller, 'create'])->name($name . '.create');
        self::post($name, [$controller, 'store'])->name($name . '.store');
        self::get($name . '/{id}', [$controller, 'show'])->name($name . '.show');
        self::get($name . '/{id}/edit', [$controller, 'edit'])->name($name . '.edit');

        // Update handles both PUT and PATCH
        self::put($name . '/{id}', [$controller, 'update'])->name($name . '.update');
        self::patch($name . '/{id}', [$controller, 'update'])->name($name . '.update');

        self::delete($name . '/{id}', [$controller, 'destroy'])->name($name . '.destroy');
    }

    /**
     * Register a fallback route to be executed if no other routes match.
     */
    public static function fallback(string|array|Closure $action): void
    {
        $instance = new RouteInstance('{fallback_any?}', $action);
        $instance->where('fallback_any', '.*');
        self::$fallbackRoute = $instance;
    }

    /**
     * Start a route group with a prefix.
     */
    public static function prefix(string $prefix): RouteGroup
    {
        return new RouteGroup(['prefix' => $prefix]);
    }

    /**
     * Start a route group that excludes global middleware.
     */
    public static function exclusive(): RouteGroup
    {
        return new RouteGroup(['exclusive' => true]);
    }

    /**
     * Start a route group with middleware.
     */
    public static function middleware(string|array|Closure $middleware): RouteGroup
    {
        return new RouteGroup(['middleware' => $middleware]);
    }

    public static function group(array|Closure $attributes, ?Closure $callback = null): void
    {
        if ($attributes instanceof Closure) {
            $callback = $attributes;
            $attributes = [];
        }

        self::$groupStack[] = $attributes;
        $callback();
        array_pop(self::$groupStack);
    }

    /**
     * Add a route to the internal store.
     */
    private static function add(string $httpMethod, string $path, string|array|Closure $action, ?string $method = null): RouteInstance
    {
        $prefix = '';
        $namePrefix = '';
        $middleware = [];
        $isExclusive = false;

        foreach (self::$groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= trim($group['prefix'], '/') . '/';
            }
            if (isset($group['as'])) {
                $namePrefix .= $group['as'];
            }
            if (isset($group['exclusive'])) {
                $isExclusive = $group['exclusive'];
            }
            if (isset($group['middleware'])) {
                $m = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $middleware = array_merge($middleware, $m);
            }
        }

        $fullPath = trim($prefix . trim($path, '/'), '/');

        $instance = new RouteInstance($fullPath, $action, $method, $namePrefix);

        if ($isExclusive) {
            $instance->onlyMiddleware($middleware);
        } elseif (! empty($middleware)) {
            $instance->middleware($middleware);
        }

        if ($instance->isStatic()) {
            self::$staticRoutes[$httpMethod][$fullPath] = $instance;
        } else {
            self::$dynamicRoutes[$httpMethod][] = $instance;
        }

        return $instance;
    }

    /**
     * Match a request against manual routes.
     */
    public static function find(string $httpMethod, string $uri): ?array
    {
        $uri = trim($uri, '/');
        $matchedPath = false;

        if (isset(self::$staticRoutes[$httpMethod][$uri])) {
            return self::$staticRoutes[$httpMethod][$uri]->resolve([]);
        }

        // Check other methods for 405
        foreach (self::$staticRoutes as $method => $routes) {
            if ($method !== $httpMethod && isset($routes[$uri])) {
                $matchedPath = true;
                break;
            }
        }

        $uriSegments = $uri === '' ? [] : explode('/', $uri);

        foreach (self::$dynamicRoutes as $method => $instances) {
            /** @var RouteInstance $instance */
            foreach ($instances as $instance) {
                $params = [];
                if ($instance->matches($uri, $uriSegments, $params)) {
                    $matchedPath = true;
                    if ($method === $httpMethod) {
                        return $instance->resolve($params);
                    }
                }
            }
        }

        return $matchedPath ? ['error' => 405] : null;
    }

    /**
     * Resolve the fallback route if it exists.
     */
    public static function resolveFallback(string $uri): ?array
    {
        if (self::$fallbackRoute) {
            return self::$fallbackRoute->resolve(['fallback_any' => $uri]);
        }

        return null;
    }

    /**
     * Register a name for a route instance.
     */
    public static function setAlias(string $name, RouteInstance $instance): void
    {
        self::$names[$name] = $instance;
    }

    /**
     * Generate a URL for a named route.
     */
    public static function getUrl(string $name, array $parameters = []): ?string
    {
        if (! isset(self::$names[$name])) {
            return null;
        }

        $instance = self::$names[$name];
        $path = $instance->getPath();

        foreach ($parameters as $key => $value) {
            $path = str_replace(['{' . $key . '}', '{' . $key . '?}'], (string) $value, $path);
        }

        // Remove any remaining optional parameters that weren't provided
        $path = preg_replace('/\/\{[a-z_?]+\}/i', '', $path);

        return '/' . ltrim($path, '/');
    }

    public static function reset(): void
    {
        self::$staticRoutes = [];
        self::$dynamicRoutes = [];
        self::$fallbackRoute = null;
        self::$groupStack = [];
        self::$names = [];
    }
}
