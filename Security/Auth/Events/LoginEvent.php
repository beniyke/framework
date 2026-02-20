<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Event fired when a user successfully logs in.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Events;

use Security\Auth\Contracts\Authenticatable;

class LoginEvent
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly bool $remember = false,
        public readonly string $guard = 'web'
    ) {
    }
}
