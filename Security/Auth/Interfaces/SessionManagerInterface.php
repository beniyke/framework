<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for authentication session persistence.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Interfaces;

use Security\Auth\Contracts\Authenticatable;

interface SessionManagerInterface
{
    /**
     * Create a new persistent session for the given user.
     */
    public function create(Authenticatable $user): string;

    /**
     * Validate the given session token and return the associated user.
     */
    public function validate(string $token): ?Authenticatable;

    /**
     * Revoke/terminate the given session token.
     */
    public function revoke(string $token): bool;
}
