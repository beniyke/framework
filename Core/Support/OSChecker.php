<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Operating System detection utility.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Support;

class OSChecker
{
    public const FAMILY_WINDOWS = 'Windows';
    public const FAMILY_LINUX = 'Linux';
    public const FAMILY_DARWIN = 'Darwin'; // macOS/iOS
    public const FAMILY_BSD = 'BSD';
    public const FAMILY_UNKNOWN = 'Unknown';

    public const OS_MAC = 'Darwin';
    public const OS_IOS = 'iOS';
    public const OS_ANDROID = 'Android';

    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === self::FAMILY_WINDOWS;
    }

    public static function isLinux(): bool
    {
        return PHP_OS_FAMILY === self::FAMILY_LINUX;
    }

    public static function isDarwin(): bool
    {
        return PHP_OS_FAMILY === self::FAMILY_DARWIN;
    }

    public static function isUnixLike(): bool
    {
        return in_array(PHP_OS_FAMILY, [
            self::FAMILY_LINUX,
            self::FAMILY_DARWIN,
            self::FAMILY_BSD,
            'Unix',
        ]);
    }

    public static function isMac(): bool
    {
        return self::isDarwin() && strpos(PHP_OS, self::OS_IOS) === false;
    }

    public static function isIOS(): bool
    {
        return self::isDarwin() && strpos(PHP_OS, self::OS_IOS) !== false;
    }

    public static function isAndroid(): bool
    {
        return strpos(PHP_OS, self::OS_ANDROID) !== false;
    }
}
