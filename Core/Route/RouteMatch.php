<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * DTO representing a matched route.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Route;

use Closure;
use Helpers\Data\DTO;

class RouteMatch extends DTO
{
    private readonly string|Closure $controller;

    private readonly string $method;

    private readonly array $parameters;

    private readonly array $middleware;

    private readonly array $context;

    private readonly bool $is_exclusive;

    public function getController(): string|Closure
    {
        return $this->controller;
    }

    public function isExclusive(): bool
    {
        return $this->is_exclusive ?? false;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
