<?php

declare(strict_types=1);

namespace Security\Auth\Contracts;

/**
 * Interface for DTOs that provide "remember me" functionality.
 */
interface ProvidesRememberMe
{
    /**
     * Determine if the user requested to be remembered.
     */
    public function hasRememberMe(): bool;
}
