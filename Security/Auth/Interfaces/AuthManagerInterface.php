<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for the authentication manager.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Interfaces;

use Closure;

interface AuthManagerInterface
{
    public function guard(?string $name = null): GuardInterface;

    public function getDefaultDriver(): string;

    /**
     * Register a custom guard creator Closure.
     */
    public function extend(string $driver, Closure $callback): self;

    /**
     * Logout from all configured guards.
     */
    public function logoutAll(): void;
}
