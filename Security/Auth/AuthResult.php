<?php

declare(strict_types=1);

namespace Security\Auth;

use Security\Auth\Contracts\Authenticatable;

/**
 * Represents the result of an authentication operation.
 */
class AuthResult
{
    public function __construct(
        private readonly bool $success,
        private readonly ?Authenticatable $user = null,
        private readonly ?string $message = null,
        private readonly array $metadata = []
    ) {
    }

    /**
     * Determine if the authentication was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Get the authenticated user, if available.
     */
    public function getUser(): ?Authenticatable
    {
        return $this->user;
    }

    /**
     * Get the authentication message (e.g., error message).
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Create a successful authentication result.
     */
    public static function success(Authenticatable $user, array $metadata = []): self
    {
        return new self(true, $user, null, $metadata);
    }

    /**
     * Create a failed authentication result.
     */
    public static function failure(string $message, array $metadata = []): self
    {
        return new self(false, null, $message, $metadata);
    }
}
