<?php

declare(strict_types=1);

namespace Testing\Mocks;

use App\Requests\LoginRequest;
use App\Services\Auth\Interfaces\AuthServiceInterface;

class AuthMock implements AuthServiceInterface
{
    public function isAuthenticated(): bool
    {
        return false;
    }

    public function user(): ?object
    {
        return null;
    }

    public function login(LoginRequest $request): bool
    {
        return false;
    }

    public function logout(?string $session_token = null): bool
    {
        return true;
    }

    public function isAuthorized(string $route): bool
    {
        return false;
    }
}
