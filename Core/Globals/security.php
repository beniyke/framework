<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Security helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Helpers\Encryption\Encrypter;

if (! function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return request()->getCsrfToken();
    }
}

if (! function_exists('encrypt')) {
    function encrypt(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => encrypt($v), $value);
        }

        return enc()->encrypt($value);
    }
}

if (! function_exists('decrypt')) {
    function decrypt(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => decrypt($v), $value);
        }

        return enc()->decrypt($value);
    }
}

if (! function_exists('enc')) {
    function enc(string $driver = 'string'): Encrypter
    {
        $encrypter = resolve(Encrypter::class);

        if ($driver === 'file') {
            $encrypter->file();
        } else {
            $encrypter->string();
        }

        return $encrypter;
    }
}
