<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for the Configuration Service.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Services;

interface ConfigServiceInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function all(): array;

    public function set(string $key, mixed $value): void;

    public function isDebugEnabled(): bool;
}
