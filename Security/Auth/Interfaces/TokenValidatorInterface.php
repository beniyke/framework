<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for validating access tokens and retrieving the authenticated user.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Interfaces;

use Security\Auth\Contracts\Authenticatable;

interface TokenValidatorInterface
{
    public function getAuthenticatedUser(): ?Authenticatable;
}
