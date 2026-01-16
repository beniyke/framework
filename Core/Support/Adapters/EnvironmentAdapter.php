<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Adapter for the Environment class to implement EnvironmentInterface.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Support\Adapters;

use Core\Support\Adapters\Interfaces\EnvironmentInterface;
use Core\Support\Environment;

class EnvironmentAdapter implements EnvironmentInterface
{
    public function current(): string
    {
        return Environment::current();
    }

    public function isProduction(): bool
    {
        return Environment::isProduction();
    }

    public function isTesting(): bool
    {
        return Environment::isTesting();
    }

    public function isDevelopment(): bool
    {
        return Environment::isDevelopment();
    }

    public function isLocal(): bool
    {
        return Environment::isLocal();
    }
}
