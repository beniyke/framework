<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Event fired when an authentication attempt fails.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Events;

class LoginFailedEvent
{
    public function __construct(
        public readonly array $credentials,
        public readonly string $guard = 'web'
    ) {
    }
}
