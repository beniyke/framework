<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Builder for contextual bindings.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Ioc;

class ContextualBindingBuilder
{
    private Container $container;

    private string $concrete;

    private string $abstract;

    public function __construct(Container $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    public function needs(string $abstract): self
    {
        $this->abstract = $abstract;

        return $this;
    }

    public function give($implementation): void
    {
        $this->container->addContextualBinding($this->concrete, $this->abstract, $implementation);
    }
}
