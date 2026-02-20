<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Authentication guard for session-based authentication.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Guards;

use Core\Services\ConfigServiceInterface;
use Helpers\Http\Session;
use Security\Auth\Contracts\Authenticatable;
use Security\Auth\Interfaces\GuardInterface;
use Security\Auth\Interfaces\SessionManagerInterface;
use Security\Auth\Interfaces\UserSourceInterface;

class SessionGuard implements GuardInterface
{
    protected ?Authenticatable $user = null;

    protected readonly string $tokenKey;

    public function __construct(
        protected string $name,
        protected UserSourceInterface $source,
        protected readonly Session $session,
        protected readonly ConfigServiceInterface $config,
        protected readonly SessionManagerInterface $sessionManager
    ) {
        $this->tokenKey = 'auth_session_token_' . $this->name;
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

        $token = $this->session->get($this->tokenKey);

        if (! is_null($token)) {
            $this->user = $this->sessionManager->validate($token);
        }

        if (is_null($this->user) && ! is_null($token)) {
            $this->logout();
        }

        return $this->user;
    }

    /**
     * Validate a user's credentials without logging them in.
     */
    public function validate(array $credentials = []): bool
    {
        return ! is_null($this->retrieveUserByCredentials($credentials));
    }

    /**
     * Attempt to authenticate a user using the given credentials and establish a session.
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

        $this->session->regenerateId();

        $token = $this->sessionManager->create($user);

        $this->session->set($this->tokenKey, $token);
    }

    public function logout(): void
    {
        $token = $this->session->get($this->tokenKey);

        if ($token) {
            $this->sessionManager->revoke($token);
        }

        $this->session->delete($this->tokenKey);
        $this->session->regenerateId();
        $this->user = null;
    }

    public function getSessionKey(): ?string
    {
        return $this->tokenKey;
    }
}
