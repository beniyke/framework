<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for token management and issuance.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Interfaces;

use Security\Auth\Contracts\Authenticatable;

interface TokenManagerInterface
{
    /**
     * Create a new token for the given user.
     */
    public function createToken(Authenticatable $user, string $name, array $abilities = ['*'], ?int $expiresInSeconds = null): string;

    /**
     * Authenticate a user by the given plain text token.
     */
    public function authenticate(string $plainTextToken, callable $userFinder): ?Authenticatable;

    public function checkAbility(string $plainTextToken, string $ability): bool;

    /**
     * Revoke a specific token by its ID.
     */
    public function revokeToken(int|string $tokenId): bool;
}
