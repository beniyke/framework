<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for Dotenv loader.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Services;

interface DotenvInterface
{
    public function load(): void;

    public function getValue(string $key, mixed $default = null): mixed;

    public function setValue(string $key, mixed $value): void;

    public function cache(): void;

    public function generateAndSaveAppKey(): void;
}
