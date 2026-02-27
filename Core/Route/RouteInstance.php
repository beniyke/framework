<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Represents a single registered route.
 *
 * @author BenIyke <beniyke34@gmail.com>
 */

namespace Core\Route;

use Closure;

class RouteInstance
{
    private array $middleware = [];

    private string $name = '';

    private array $wheres = [];

    private array $excludedMiddleware = [];

    private bool $isExclusive = false;

    private array $segments = [];

    private bool $isStatic = true;

    public function __construct(
        private readonly string $path,
        private readonly string|array|Closure $action,
        private readonly ?string $controllerMethod = null,
        private readonly string $namePrefix = ''
    ) {
        $cleanPath = trim($this->path, '/');
        if ($cleanPath !== '') {
            $this->segments = explode('/', $cleanPath);
        }

        $this->isStatic = !str_contains($this->path, '{');
    }

    /**
     * Attach middleware to the route.
     */
    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);

        return $this;
    }

    /**
     * Exclude specific global middleware.
     */
    public function withoutMiddleware(string|array $middleware): self
    {
        $this->excludedMiddleware = array_merge($this->excludedMiddleware, (array) $middleware);

        return $this;
    }

    /**
     * Use ONLY the provided middleware, bypassing global defaults.
     */
    public function onlyMiddleware(string|array $middleware): self
    {
        $this->isExclusive = true;
        $this->middleware = (array) $middleware;

        return $this;
    }

    /**
     * Assign a name to the route.
     */
    public function name(string $name): self
    {
        $this->name = $this->namePrefix . $name;
        Route::setAlias($this->name, $this);

        return $this;
    }

    /**
     * Add regex constraints for parameters.
     */
    public function where(array|string $name, ?string $expression = null): self
    {
        if (is_array($name)) {
            $this->wheres = array_merge($this->wheres, $name);
        } else {
            $this->wheres[$name] = $expression;
        }

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isStatic(): bool
    {
        return $this->isStatic;
    }

    public function matches(string $uri, array $uriSegments, array &$params): bool
    {
        $path = trim($this->path, '/');

        if ($path === $uri) {
            return true;
        }

        // If it didn't match exactly and it's static, it's not a match.
        if ($this->isStatic) {
            return false;
        }

        $uriCount = count($uriSegments);
        $routeCount = count($this->segments);

        // If URI is longer than route, it can't match
        if ($uriCount > $routeCount) {
            return false;
        }

        foreach ($this->segments as $index => $segment) {
            $isOptional = str_ends_with($segment, '?}');
            $cleanSegment = $isOptional ? str_replace('?}', '}', $segment) : $segment;

            if (str_starts_with($cleanSegment, '{')) {
                $paramName = substr($cleanSegment, 1, -1);
                $value = $uriSegments[$index] ?? null;

                if ($value === null) {
                    if ($isOptional) {
                        continue;
                    }

                    return false;
                }

                if (isset($this->wheres[$paramName]) && ! preg_match('/^' . $this->wheres[$paramName] . '$/', $value)) {
                    return false;
                }

                $params[$paramName] = $value;

                continue;
            }

            if (! isset($uriSegments[$index]) || $segment !== $uriSegments[$index]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve the route into a standard match array.
     */
    public function resolve(array $params): array
    {
        if ($this->action instanceof Closure) {
            return [
                'controller' => $this->action,
                'method' => '__invoke',
                'parameters' => array_values($params),
                'middleware' => $this->middleware,
                'excluded_middleware' => $this->excludedMiddleware,
                'is_exclusive' => $this->isExclusive,
            ];
        }

        $moduleController = '';
        $method = $this->controllerMethod;

        if (is_array($this->action)) {
            [$moduleController, $method] = array_pad($this->action, 2, null);
        } elseif (is_string($this->action) && str_contains($this->action, '@')) {
            [$moduleController, $method] = explode('@', $this->action);
        } else {
            $moduleController = $this->action;
            $method = $method ?? 'index';
        }

        // Handle FQCN vs Shortcut
        if (str_starts_with($moduleController, 'App\\') || class_exists($moduleController)) {
            $fqcn = $moduleController;
            if (!str_contains($fqcn, 'Controller') && !class_exists($fqcn)) {
                $fqcn .= 'Controller';
            }
        } else {
            $parts = explode('\\', $moduleController);
            $module = ucfirst($parts[0]);
            $controller = isset($parts[1]) ? ucfirst($parts[1]) : $module;

            $fqcn = str_replace(
                ['{module}', '{controller}'],
                [$module, $controller],
                Route::getControllerNamespace()
            );
        }

        return [
            'controller' => $fqcn,
            'method' => $method ?? 'index',
            'parameters' => array_values($params),
            'middleware' => $this->middleware,
            'excluded_middleware' => $this->excludedMiddleware,
            'is_exclusive' => $this->isExclusive,
        ];
    }
}
