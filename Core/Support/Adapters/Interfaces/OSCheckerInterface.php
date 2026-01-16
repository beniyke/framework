<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for OSChecker.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Support\Adapters\Interfaces;

interface OSCheckerInterface
{
    public function isWindows(): bool;

    public function isLinux(): bool;

    public function isDarwin(): bool;

    public function isUnixLike(): bool;

    public function isMac(): bool;

    public function isIOS(): bool;

    public function isAndroid(): bool;
}
