<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Adapter for OSChecker.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Support\Adapters;

use Core\Support\Adapters\Interfaces\OSCheckerInterface;
use Core\Support\OSChecker;

class OSCheckerAdapter implements OSCheckerInterface
{
    public function isWindows(): bool
    {
        return OSChecker::isWindows();
    }

    public function isLinux(): bool
    {
        return OSChecker::isLinux();
    }

    public function isDarwin(): bool
    {
        return OSChecker::isDarwin();
    }

    public function isUnixLike(): bool
    {
        return OSChecker::isUnixLike();
    }

    public function isMac(): bool
    {
        return OSChecker::isMac();
    }

    public function isIOS(): bool
    {
        return OSChecker::isIOS();
    }

    public function isAndroid(): bool
    {
        return OSChecker::isAndroid();
    }
}
