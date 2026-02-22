<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fake authentication manager and guard for testing.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use Closure;
use PHPUnit\Framework\Assert as PHPUnit;
use Security\Auth\Contracts\Authenticatable;
use Security\Auth\Interfaces\AuthManagerInterface;
use Security\Auth\Interfaces\GuardInterface;

class AuthFake implements AuthManagerInterface
{
    /**
     * @var array<string, GuardFake>
     */
    protected array $guards = [];

    protected string $defaultGuard = 'web';

    public function guard(?string $name = null): GuardInterface
    {
        $name = $name ?: $this->defaultGuard;

        return $this->guards[$name] ??= new GuardFake($name);
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultGuard;
    }

    public function extend(string $driver, Closure $callback): self
    {
        return $this;
    }

    public function logoutAll(): void
    {
        foreach ($this->guards as $guard) {
            $guard->logout();
        }
    }

    /**
     * Set the currently authenticated user for a guard.
     */
    public function actingAs(Authenticatable $user, ?string $guard = null): self
    {
        $this->guard($guard)->setUser($user);

        return $this;
    }

    /**
     * Assert that a user is authenticated.
     */
    public function assertAuthenticated(?string $guard = null): void
    {
        PHPUnit::assertTrue(
            $this->guard($guard)->check(),
            'The user is not authenticated.'
        );
    }

    /**
     * Assert that a user is not authenticated.
     */
    public function assertGuest(?string $guard = null): void
    {
        PHPUnit::assertFalse(
            $this->guard($guard)->check(),
            'The user is unexpectedly authenticated.'
        );
    }
}

class GuardFake implements GuardInterface
{
    protected ?Authenticatable $user = null;

    public function __construct(protected string $name)
    {
    }

    public function check(): bool
    {
        return ! is_null($this->user);
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function validate(array $credentials = []): bool
    {
        return true;
    }

    public function attempt(array $credentials = []): bool
    {
        return true;
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    public function logout(): void
    {
        $this->user = null;
    }

    public function getSessionKey(): ?string
    {
        return "auth_{$this->name}";
    }
}
