<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Facade for the file logger system.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers;

use Helpers\File\FileLogger;

class Log
{
    private static ?FileLogger $logger = null;

    public static function instance(): FileLogger
    {
        if (self::$logger === null) {
            self::$logger = new FileLogger();
        }

        return self::$logger;
    }

    /**
     * Get a logger instance for a specific channel/file.
     * Automatically attempts to resolve the file extension.
     */
    public static function channel(string $file): FileLogger
    {
        if (! str_ends_with($file, '.log')) {
            $file .= '.log';
        }

        return new FileLogger($file);
    }

    public static function info(string $message, array $context = []): void
    {
        self::instance()->info($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::instance()->error($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::instance()->warning($message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::instance()->critical($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::instance()->debug($message, $context);
    }
}
