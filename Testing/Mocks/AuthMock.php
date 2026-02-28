<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class AuthMock implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Mocks;

use Core\Contracts\AuthServiceInterface;
use Helpers\Data\Contracts\DataTransferObject;
use Security\Auth\AuthResult;
use Security\Auth\Contracts\Authenticatable;

class AuthMock implements AuthServiceInterface
{
    public function isAuthenticated(): bool
    {
        return false;
    }

    public function viaGuard(string $guard): self
    {
        return $this;
    }

    public function user(): ?Authenticatable
    {
        return null;
    }

    public function login(DataTransferObject $request): AuthResult
    {
        return AuthResult::failure('Mock login failed.');
    }

    public function logout(): bool
    {
        return true;
    }

    public function logoutAll(): bool
    {
        return true;
    }

    public function isAuthorized(string $resource = ''): bool
    {
        return false;
    }

    public function getSessionKey(): ?string
    {
        return null;
    }

    public function passwordNeedsUpdate(): bool
    {
        return false;
    }
}
