<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Authentication guard for token-based authentication (API).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Guards;

use Helpers\Http\Request;
use Security\Auth\Contracts\Authenticatable;
use Security\Auth\Interfaces\GuardInterface;
use Security\Auth\Interfaces\TokenValidatorInterface;
use Security\Auth\Interfaces\UserSourceInterface;

class TokenGuard implements GuardInterface
{
    protected ?Authenticatable $user = null;

    public function __construct(
        protected string $name,
        protected UserSourceInterface $source,
        protected readonly Request $request,
        protected readonly TokenValidatorInterface $tokenValidator
    ) {
    }

    public function check(): bool
    {
        return ! is_null($this->user());
    }

    public function user(): ?Authenticatable
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $user = $this->tokenValidator->getAuthenticatedUser();

        if ($user instanceof Authenticatable) {
            $this->user = $user;
        }

        return $this->user;
    }

    /**
     * Validate a user's credentials without logging them in.
     */
    public function validate(array $credentials = []): bool
    {
        if (empty($credentials)) {
            return false;
        }

        return ! is_null($this->retrieveUserByCredentials($credentials));
    }

    /**
     * Attempt to authenticate a user using the given credentials and set the user for the request.
     */
    public function attempt(array $credentials = []): bool
    {
        if ($user = $this->retrieveUserByCredentials($credentials)) {
            $this->setUser($user);

            return true;
        }

        return false;
    }

    /**
     * Retrieve a user if the given credentials are valid and the user can authenticate.
     */
    protected function retrieveUserByCredentials(array $credentials): ?Authenticatable
    {
        $user = $this->source->retrieveByCredentials($credentials);

        if ($user && $this->source->validateCredentials($user, $credentials) && $user->canAuthenticate()) {
            return $user;
        }

        return null;
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
        return null;
    }
}
