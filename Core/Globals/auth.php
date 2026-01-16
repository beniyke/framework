<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Authentication helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use App\Services\Auth\Interfaces\AuthServiceInterface;

if (! function_exists('auth')) {
    /**
     * Get the authentication service instance.
     */
    function auth(): AuthServiceInterface
    {
        return resolve(AuthServiceInterface::class);
    }
}
