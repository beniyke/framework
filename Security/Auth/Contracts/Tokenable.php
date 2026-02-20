<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for entities that can issue and manage access tokens.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Contracts;

use Security\Auth\Interfaces\AccessTokenInterface;

interface Tokenable
{
    public function getTokenableId(): int|string;

    public function getTokenableType(): string;

    public function withAccessToken(AccessTokenInterface $token): self;

    public function currentAccessToken(): ?AccessTokenInterface;

    public function tokenCan(string $ability): bool;
}
