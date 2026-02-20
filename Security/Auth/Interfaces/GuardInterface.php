<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for authentication guards.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Interfaces;

use Security\Auth\Contracts\Authenticatable;

interface GuardInterface
{
    public function check(): bool;

    public function user(): ?Authenticatable;

    public function validate(array $credentials = []): bool;

    public function attempt(array $credentials = []): bool;

    public function setUser(Authenticatable $user): void;

    public function logout(): void;

    public function getSessionKey(): ?string;
}
