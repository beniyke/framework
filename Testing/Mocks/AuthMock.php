<?php

declare(strict_types=1);

namespace Testing\Mocks;

use App\Services\Auth\Interfaces\AuthServiceInterface;
use Helpers\Data\Contracts\DataTransferObject;

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

    public function user(): ?object
    {
        return null;
    }

    public function login(DataTransferObject $request): bool
    {
        return false;
    }

    public function logout(): bool
    {
        return true;
    }

    public function logoutAll(): bool
    {
        return true;
    }

    public function isAuthorized(string $route): bool
    {
        return false;
    }

    public function getSessionKey(): ?string
    {
        return null;
    }
}
