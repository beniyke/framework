<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Authentication helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Helpers\Http\Request;
use Security\Auth\Interfaces\AuthManagerInterface;
use Security\Auth\Interfaces\GuardInterface;

if (! function_exists('auth')) {
    /**
     * Get the authentication manager instance.
     */
    function auth(?string $guard = null): AuthManagerInterface|GuardInterface
    {
        $auth = resolve(AuthManagerInterface::class);

        if (is_null($guard)) {
            $request = resolve(Request::class);
            $guard = $request->getRouteContext('auth_guard');
        }

        return $guard ? $auth->guard($guard) : $auth;
    }
}
