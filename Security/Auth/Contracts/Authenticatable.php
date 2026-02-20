<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for authenticatable entities (e.g., User).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Contracts;

interface Authenticatable
{
    public function getAuthId(): int|string;

    public function getAuthPassword(): string;

    public function getAuthIdentifierName(): string;

    public function canAuthenticate(): bool;
}
