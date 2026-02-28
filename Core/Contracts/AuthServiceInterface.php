<?php

declare(strict_types=1);

namespace Core\Contracts;

use Helpers\Data\Contracts\DataTransferObject;
use Security\Auth\AuthResult;
use Security\Auth\Contracts\Authenticatable;

/**
 * Universal interface for authentication services.
 */
interface AuthServiceInterface
{
    public function isAuthenticated(): bool;

    /**
     * Switch to a specific authentication guard.
     */
    public function viaGuard(string $guard): self;

    public function user(): ?Authenticatable;

    /**
     * Attempt to log in a user with the given credentials.
     */
    public function login(DataTransferObject $request): AuthResult;

    /**
     * Log the current user out.
     */
    public function logout(): bool;

    /**
     * Log out from all active guards.
     */
    public function logoutAll(): bool;

    /**
     * Check if the authenticated user is authorized for a specific route/resource.
     */
    public function isAuthorized(string $resource = ''): bool;

    public function getSessionKey(): ?string;

    public function passwordNeedsUpdate(): bool;
}
