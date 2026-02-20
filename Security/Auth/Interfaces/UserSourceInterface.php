<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for user sources (e.g., database, LDAP).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Interfaces;

use Security\Auth\Contracts\Authenticatable;

interface UserSourceInterface
{
    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById(int|string $id): ?Authenticatable;

    /**
     * Retrieve a user by their unique credentials.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;

    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool;
}
