<?php

/**
 * Anchor Framework
 *
 * Environment helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

declare(strict_types=1);

use Core\Ioc\Container;
use Core\Services\ConfigServiceInterface;

if (! function_exists('container')) {
    function container(): object
    {
        return Container::getResolvedInstance();
    }
}

if (! function_exists('resolve')) {
    function resolve(string $namespace): object
    {
        return container()->get($namespace);
    }
}

if (! function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $service = resolve(ConfigServiceInterface::class);

        if ($key === null) {
            return $service;
        }

        return $service->get($key, $default);
    }
}

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        if (! is_string($value)) {
            return $value;
        }

        $handleTypedValue = function (string $value): mixed {
            if (strpos($value, ',') !== false) {
                return array_map('trim', explode(',', $value));
            }

            if (is_numeric($value)) {
                return strpos($value, '.') !== false ? (float) $value : (int) $value;
            }

            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                return trim($value, '"');
            }

            if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                return trim($value, "'");
            }

            return $value;
        };

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $handleTypedValue($value),
        };
    }
}
