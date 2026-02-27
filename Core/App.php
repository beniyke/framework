<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class acts as the entry point for the application's execution.
 * It resolves incoming requests using the Route class, dispatches them to the
 * appropriate controller and method, and sends the resulting response back to the client.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core;

use Closure;
use Core\Events\KernelTerminateEvent;
use Core\Ioc\ContainerInterface;
use Core\Middleware\MiddlewarePipeline;
use Core\Middleware\MiddlewareResolver;
use Core\Route\RouteMatch;
use Core\Route\UrlResolver;
use Core\Services\ConfigServiceInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Helpers\Http\Flash;
use Helpers\Http\Request;
use Helpers\Http\Response;
use Helpers\String\Inflector;

class App
{
    public const VERSION = '2.6.0';

    private ContainerInterface $container;

    private UrlResolver $router;

    private Request $request;

    private Response $response;

    private readonly PathResolverInterface $paths;

    private readonly FileReadWriteInterface $fileReadWrite;

    private readonly Flash $flash;

    public function __construct(ContainerInterface $container, PathResolverInterface $paths, FileReadWriteInterface $fileReadWrite)
    {
        $this->container = $container;
        $this->router = $container->get(UrlResolver::class);
        $this->request = $container->get(Request::class);
        $this->response = $container->get(Response::class);
        $this->flash = $container->get(Flash::class);
        $this->paths = $paths;
        $this->fileReadWrite = $fileReadWrite;
    }

    public function run(): void
    {
        $this->handle($this->request)
            ->complete(function () {
                Event::dispatch(new KernelTerminateEvent($this->request, $this->response));

                if ($this->container->has(Kernel::class)) {
                    $this->container->get(Kernel::class)->terminate();
                }
            });
    }

    public function handle(Request $request): Response
    {
        $this->request = $request;
        $this->router->syncRequest($request);

        $resolvedRoute = $this->router->resolve();

        return $this->handleResolution($resolvedRoute);
    }

    private function handleResolution(null|RouteMatch|array $resolvedRoute): Response
    {
        if ($resolvedRoute === null) {
            return $this->handleNotFoundResponse();
        }

        if (is_array($resolvedRoute)) {
            if (! empty($resolvedRoute['redirect'])) {
                return $this->response->redirect($resolvedRoute['redirect']);
            }

            if (isset($resolvedRoute['error']) && $resolvedRoute['error'] === 405) {
                return $this->handleMethodNotAllowedResponse();
            }
        }

        $match = $resolvedRoute;

        $this->hydrateRouteContext($this->request, $match);

        // Bypass security checks for exclusive manual routes
        $isExclusive = ($match instanceof RouteMatch) ? $match->isExclusive() : ($match['is_exclusive'] ?? false);

        if (! $isExclusive && $this->request->isStateChanging()) {
            if (! $this->request->isSecurityValid()) {
                return $this->handleSecurityFailure();
            }
        }

        return $this->processThroughMiddleware($match);
    }

    private function handleMethodNotAllowedResponse(): Response
    {
        if ($this->request->expectsJson()) {
            return $this->response
                ->status(405)
                ->json([
                    'status' => false,
                    'message' => 'Method not allowed',
                    'error' => '405 Method Not Allowed'
                ]);
        }

        $not_allowed = $this->paths->coreViewTemplatePath('405.html');
        $content = $this->fileReadWrite->get($not_allowed);

        return $this->response
            ->header(['Content-Type' => 'text/html; charset=UTF-8'])
            ->status(405)
            ->body($content);
    }

    private function handleNotFoundResponse(): Response
    {
        if ($this->request->expectsJson()) {
            return $this->response
                ->status(404)
                ->json([
                    'status' => false,
                    'message' => 'Resource not found',
                    'error' => '404 Not Found'
                ]);
        }

        $not_found = $this->paths->coreViewTemplatePath('notfound.html');
        $content = $this->fileReadWrite->get($not_found);

        return $this->response
            ->header(['Content-Type' => 'text/html; charset=UTF-8'])
            ->notFound($content);
    }

    private function handleSecurityFailure(): Response
    {
        $referer_route = $this->request->refererRoute();
        $fallback_route = '/';
        $target_route = $referer_route ?? $fallback_route;
        $target_url = $this->request->baseUrl($target_route);

        $this->flash->error('Security check failed. Please try again.');

        return $this->response->redirect($target_url);
    }

    private function processThroughMiddleware(RouteMatch $match): Response
    {
        $middlewares = $match->getMiddleware();

        if (empty($middlewares)) {
            return $this->dispatch($match);
        }

        // Resolve middleware groups, aliases, and closures
        $resolver = new MiddlewareResolver($this->container->get(ConfigServiceInterface::class));
        $middlewares = $resolver->resolve($middlewares);

        $dispatch = function (Request $request) use ($match) {
            return $this->dispatch($match);
        };

        $pipeline = new MiddlewarePipeline($this->container);

        return $pipeline
            ->send($this->request, $this->response)
            ->through($middlewares)
            ->then($dispatch);
    }

    private function hydrateRouteContext(Request $request, RouteMatch $match): void
    {
        $controller = $match->getController();
        $method = $match->getMethod();

        if (is_string($controller) && preg_match('/App\\\\([^\\\\]+)\\\\Controllers\\\\([^\\\\]+)Controller/', $controller, $matches)) {
            $domain = $matches[1];
            $entity = $matches[2];

            $request->setRouteContext('domain', $domain);
            $request->setRouteContext('entity', $entity);
            $request->setRouteContext('resource', Inflector::pluralize(strtolower($entity)));
            $request->setRouteContext('action', $method);
        }

        // Apply explicit context overrides from the route match if present
        foreach ($match->getContext() as $key => $value) {
            $request->setRouteContext($key, $value);
        }
    }

    private function dispatch(RouteMatch $match): Response
    {
        $controller = $match->getController();
        $parameters = $match->getParameters();

        if ($controller instanceof Closure) {
            $result = $this->container->call($controller, $parameters);
        } else {
            $instance = $this->container->get($controller);
            $method = $match->getMethod();
            $result = $this->container->call([$instance, $method], $parameters);
        }

        return $result instanceof Response ? $result : new Response((string) $result);
    }
}
