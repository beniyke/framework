<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Middleware pipeline runner.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Middleware;

use Closure;
use Core\Ioc\ContainerInterface;
use Helpers\Http\Request;
use Helpers\Http\Response;
use RuntimeException;

class MiddlewarePipeline
{
    private ContainerInterface $container;

    private array $middlewareStack;

    private Request $request;

    private Response $response;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function send(Request $request, Response $response): self
    {
        $this->request = $request;
        $this->response = $response;

        return $this;
    }

    public function through(array $middlewareStack): self
    {
        $this->middlewareStack = $middlewareStack;

        return $this;
    }

    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewareStack),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->request, $this->response);
    }

    private function carry(): Closure
    {
        return function (Closure $stack, string $middleware) {
            return function (Request $request, Response $response) use ($stack, $middleware) {
                $instance = $this->container->get($middleware);

                if (!$instance instanceof MiddlewareInterface) {
                    throw new RuntimeException(
                        sprintf(
                            'Middleware [%s] must implement %s',
                            $middleware,
                            MiddlewareInterface::class
                        )
                    );
                }

                return $instance->handle($request, $response, $stack);
            };
        };
    }

    private function prepareDestination(Closure $destination): Closure
    {
        return function (Request $request, Response $response) use ($destination) {
            return $destination($request, $response);
        };
    }
}
