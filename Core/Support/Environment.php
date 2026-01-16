<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Environment detection and helper class.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Support;

class Environment
{
    public const ENV_PRODUCTION = 'prod';
    public const ENV_TEST = 'test';
    public const ENV_DEVELOPMENT = 'dev';

    protected static function getBaseEnv(): string
    {
        $env = getenv('APP_ENV') ?: self::ENV_DEVELOPMENT;

        return strtolower($env);
    }

    public static function current(): string
    {
        return self::getBaseEnv();
    }

    public static function isProduction(): bool
    {
        return self::getBaseEnv() === self::ENV_PRODUCTION;
    }

    public static function isTesting(): bool
    {
        return self::getBaseEnv() === self::ENV_TEST;
    }

    public static function isDevelopment(): bool
    {
        return self::getBaseEnv() === self::ENV_DEVELOPMENT;
    }

    public static function isLocal(): bool
    {
        $env = self::getBaseEnv();

        return $env === self::ENV_DEVELOPMENT || $env === self::ENV_TEST;
    }
}
