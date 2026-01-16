<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class provides a way to output messages to the console with optional background.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Helpers;

class Output
{
    public static function success(string $message): void
    {
        static::info($message, 'green');
        exit(0);
    }

    public static function error(string $message): void
    {
        static::info($message, 'red');
        exit(1);
    }

    public static function info(string $data, string $background = '', bool $newline = true): void
    {
        if (self::isCli()) {
            ConsoleColor::log($data, 'white', $newline, $background);
        } else {
            echo "{$data}\n";
        }
    }

    private static function isCli(): bool
    {
        return php_sapi_name() === 'cli';
    }
}
