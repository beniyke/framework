<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for access token entities.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Interfaces;

interface AccessTokenInterface
{
    /**
     * Determine if the token has a specific ability.
     */
    public function can(string $ability): bool;
}
