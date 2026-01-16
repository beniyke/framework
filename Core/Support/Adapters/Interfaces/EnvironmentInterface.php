<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for Environment detection.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Support\Adapters\Interfaces;

interface EnvironmentInterface
{
    public function current(): string;

    public function isProduction(): bool;

    public function isTesting(): bool;

    public function isDevelopment(): bool;

    public function isLocal(): bool;
}
