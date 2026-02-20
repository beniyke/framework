<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Global path helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Helpers\File\Paths;

if (! function_exists('base_path')) {
    function base_path(?string $path = null): string
    {
        return Paths::basePath($path);
    }
}

if (! function_exists('app_path')) {
    function app_path(?string $path = null): string
    {
        return Paths::appPath($path);
    }
}

if (! function_exists('storage_path')) {
    function storage_path(?string $path = null): string
    {
        return Paths::storagePath($path);
    }
}

if (! function_exists('public_path')) {
    function public_path(?string $path = null): string
    {
        return Paths::publicPath($path);
    }
}

if (! function_exists('config_path')) {
    function config_path(?string $path = null): string
    {
        return Paths::configPath($path);
    }
}

if (! function_exists('system_path')) {
    function system_path(?string $path = null): string
    {
        return Paths::systemPath($path);
    }
}
