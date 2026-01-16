<?php

declare(strict_types=1);

/**
 * Encapsulates the logic for resolving URLs to controller methods
 * within the application.
 *
 * @author BenIyke <beniyke34@gmail.com> | (twitter:@BigBeniyke)
 */

namespace Core\Route;

use Core\Route\Traits\RouteTrait;
use Core\Services\ConfigServiceInterface;
use Helpers\File\Paths;
use Helpers\Http\Request;

class UrlResolver
{
    use RouteTrait;

    private const CONFIG_DEFAULT = 'default';
    private const CONFIG_REDIRECT = 'redirect';
    private const CONFIG_SUBSTITUTE = 'substitute';
    private const RESULT_REDIRECT = 'redirect';

    private array $url = [];

    private ConfigServiceInterface $config;

    private Request $request;

    private array $route_config = [];

    private static array $controller_cache = [];

    private static array $method_cache = [];

    private static array $module_cache = [];
    private const CONTROLLER_NAMESPACE = 'App\\{module}\\Controllers';

    public function __construct(Request $request, ConfigServiceInterface $config)
    {
        $this->request = $request;
        $this->config = $config;

        $this->route_config = [
            self::CONFIG_DEFAULT => $config->get('route.' . self::CONFIG_DEFAULT),
            self::CONFIG_REDIRECT => $config->get('route.' . self::CONFIG_REDIRECT, []),
            self::CONFIG_SUBSTITUTE => $config->get('route.' . self::CONFIG_SUBSTITUTE, []),
        ];

        $uri = static::first(explode('?', ltrim($request->uri(), '/'))) ?? '';
        $this->url = $this->parseUrl($uri);
    }

    public function route(): string
    {
        return $this->request->route();
    }

    public function syncRequest(Request $request): void
    {
        $this->request = $request;
        $uri = static::first(explode('?', ltrim($request->uri(), '/'))) ?? '';
        $this->url = $this->parseUrl($uri);
    }

    public function config(string $key): mixed
    {
        return $this->config->get($key);
    }

    private function parseUrl(string $uri): array
    {
        $uri = rtrim(ltrim($uri, '/'), '/');

        return $uri ? explode('/', filter_var($uri, FILTER_SANITIZE_URL)) : [];
    }

    public function resolve(): RouteMatch|array|null
    {
        if (empty($this->url)) {
            return $this->resolveEmptyUrl();
        }

        $this->determineRelatedRoute();

        return $this->resolveUrlWithSegments();
    }

    private function resolveEmptyUrl(): RouteMatch|array|null
    {
        $converted_route = static::convertRouteToControllerAndModule($this->route_config[self::CONFIG_DEFAULT]);
        $controller_name = $converted_route['controller'];
        $module = $converted_route['module'];
        $controller_namespace = static::determineControllerNamespace($module);

        if (static::controllerExists($controller_name, $controller_namespace)) {
            return new RouteMatch([
                'controller' => $controller_namespace . '\\' . static::className($controller_name),
                'method' => 'index',
                'parameters' => [],
                'middleware' => $this->getMiddlewareStack() ?? [],
            ]);
        }

        return isset($this->route_config[self::CONFIG_REDIRECT][''])
            ? [self::RESULT_REDIRECT => $this->route_config[self::CONFIG_REDIRECT]['']]
            : null;
    }

    private function determineRelatedRoute(): void
    {
        $default_route = static::convertRouteToControllerAndModule($this->route_config[self::CONFIG_DEFAULT]);
        $first_url_segment = $this->url[0];

        if ($default_route['controller'] === $first_url_segment) {
            $this->url = $this->parseUrl($this->route_config[self::CONFIG_DEFAULT]);

            return;
        }

        $is_default_route_match = $default_route['module'] === $first_url_segment;
        $is_default_controller_match = ($this->url[1] ?? null) === $default_route['controller'];

        if (! $is_default_route_match || ! $is_default_controller_match) {
            $controller_namespace = static::determineControllerNamespace($default_route['module']);
            $resolved_controller = $controller_namespace . '\\' . static::className($default_route['controller']);

            if (static::methodExists($resolved_controller, $first_url_segment)) {
                $full_url = $this->route_config[self::CONFIG_DEFAULT] . '/' . implode('/', $this->url);
                $this->url = $this->parseUrl($full_url);

                return;
            }
        }
    }

    private function resolveUrlWithSegments(): RouteMatch|array|null
    {
        $url = $this->getSubstitutedUrl();
        $controller_index = 1;
        $redirect = $this->route_config[self::CONFIG_REDIRECT];

        if (isset($redirect[$url])) {
            return [self::RESULT_REDIRECT => $redirect[$url]];
        }

        $module = static::formatString($url);

        if (! static::isModule($module)) {
            return null;
        }

        $module = ucfirst($module);
        $controller_name = $this->url[$controller_index] ?? '';
        $controller_namespace = static::determineControllerNamespace($module);

        if (! static::controllerExists($controller_name, $controller_namespace)) {
            return null;
        }

        return $this->buildRouteResult($controller_name, $controller_namespace, $controller_index + 1);
    }

    private function getSubstitutedUrl(): string
    {
        $substitute = isset($this->route_config[self::CONFIG_SUBSTITUTE][$this->url[0]]);

        return $substitute ? $this->route_config[self::CONFIG_SUBSTITUTE][$this->url[0]] : $this->url[0];
    }

    private function buildRouteResult(string $controller_name, string $controller_namespace, int $param_index): RouteMatch
    {
        $parameters = array_slice($this->url, $param_index);
        $controller = $controller_namespace . '\\' . static::className($controller_name);
        $method = 'index';

        $first_param = static::formatString(static::first($parameters));
        if ($first_param && static::methodExists($controller, $first_param)) {
            $method = $first_param;
            $parameters = array_slice($parameters, 1);
        }

        return new RouteMatch([
            'controller' => $controller,
            'method' => $method,
            'parameters' => $parameters,
            'middleware' => $this->getMiddlewareStack() ?? [],
        ]);
    }

    private static function formatClass(?string $class): ?string
    {
        if (! $class) {
            return null;
        }

        $class = str_replace('_', '', ucwords($class, '_'));

        return lcfirst($class);
    }

    private static function formatString(?string $string): ?string
    {
        return $string ? str_replace('-', '_', $string) : null;
    }

    private static function first(array $array): ?string
    {
        return reset($array) ?: null;
    }

    private static function methodExists(string $controller, string $method): bool
    {
        $key = $controller . '::' . $method;
        if (! isset(self::$method_cache[$key])) {
            self::$method_cache[$key] = method_exists($controller, $method);
        }

        return self::$method_cache[$key];
    }

    private static function controllerExists(string $class_name, string $namespace): bool
    {
        $key = $namespace . '\\' . static::className($class_name);
        if (! isset(self::$controller_cache[$key])) {
            self::$controller_cache[$key] = class_exists($key);
        }

        return self::$controller_cache[$key];
    }

    private static function className(string $class_name): string
    {
        $class_name = static::formatString($class_name);
        $class_name = static::formatClass($class_name);

        return ucfirst($class_name ?? '') . 'Controller';
    }

    private static function isModule(string $string): bool
    {
        if (! isset(self::$module_cache[$string])) {
            $dir = Paths::appSourcePath(ucfirst($string));
            self::$module_cache[$string] = is_dir($dir);
        }

        return self::$module_cache[$string];
    }

    private static function convertRouteToControllerAndModule(string $route): array
    {
        [$module, $controller] = array_pad(explode('/', $route), 2, null);

        return compact('module', 'controller');
    }

    private static function determineControllerNamespace(string $module): string
    {
        return str_replace('{module}', ucfirst($module), static::CONTROLLER_NAMESPACE);
    }
}
