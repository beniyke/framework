<?php

declare(strict_types=1);

namespace Security\Auth;

use Core\Contracts\AuthServiceInterface;
use Core\Event;
use Core\Services\ConfigServiceInterface;
use Helpers\Data\Contracts\DataTransferObject;
use Helpers\Http\Request;
use Security\Auth\Contracts\Authenticatable;
use Security\Auth\Contracts\ProvidesRememberMe;
use Security\Auth\Events\LoginEvent;
use Security\Auth\Events\LoginFailedEvent;
use Security\Auth\Events\LogoutEvent;
use Security\Auth\Interfaces\AuthManagerInterface;
use Security\Auth\Interfaces\TokenManagerInterface;

/**
 * Core Authentication Service.
 *
 * This service handles both Web (Session) and API (Token) authentication
 * by interacting with the AuthManager.
 */
class AuthService implements AuthServiceInterface
{
    private string $guard = 'web';

    public function __construct(
        private readonly AuthManagerInterface $auth,
        private readonly Request $request,
        private readonly ConfigServiceInterface $config,
        private readonly ?TokenManagerInterface $token_manager = null
    ) {
        $this->guard = $this->config->get('auth.defaults.guard', 'web');
    }

    /**
     * {@inheritDoc}
     */
    public function viaGuard(string $guard): self
    {
        $this->guard = $guard;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isAuthenticated(): bool
    {
        return $this->auth->guard($this->guard)->check();
    }

    /**
     * {@inheritDoc}
     */
    public function user(): ?Authenticatable
    {
        return $this->auth->guard($this->guard)->user();
    }

    /**
     * {@inheritDoc}
     */
    public function login(DataTransferObject $request): AuthResult
    {
        if (! $request->isValid()) {
            Event::dispatch(new LoginFailedEvent($request->toArray(), $this->guard));

            return AuthResult::failure('Invalid login credentials.');
        }

        if (! $this->auth->guard($this->guard)->attempt($request->toArray())) {
            Event::dispatch(new LoginFailedEvent($request->toArray(), $this->guard));

            return AuthResult::failure('Invalid login credentials.');
        }

        $user = $this->user();
        $metadata = [];

        // Handle API token generation if using a token-based guard
        if ($this->isTokenGuard() && $this->token_manager) {
            $tokenName = $this->request->post('device_name') ?? 'API Client';
            $abilities = $this->request->post('abilities') ?? ['*'];

            $metadata['token'] = $this->token_manager->createToken(
                $user,
                $tokenName,
                $abilities
            );
        }

        $remember = ($request instanceof ProvidesRememberMe) && $request->hasRememberMe();
        Event::dispatch(new LoginEvent($user, $remember, $this->guard));

        return AuthResult::success($user, $metadata);
    }

    /**
     * {@inheritDoc}
     */
    public function logout(): bool
    {
        $user = $this->user();

        if ($user) {
            // Revoke API token if applicable
            if ($this->isTokenGuard() && $this->token_manager) {
                $this->revokeCurrentToken();
            }

            $this->auth->guard($this->guard)->logout();
            Event::dispatch(new LogoutEvent($user, $this->guard));
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function logoutAll(): bool
    {
        $guards = $this->config->get('auth.guards', []);

        foreach (array_keys($guards) as $guardName) {
            $this->viaGuard($guardName)->logout();
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isAuthorized(string $resource = ''): bool
    {
        if (! $this->isAuthenticated()) {
            return false;
        }

        $user = $this->user();

        if (! $user->canAuthenticate()) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getSessionKey(): ?string
    {
        return $this->auth->guard($this->guard)->getSessionKey();
    }

    public function passwordNeedsUpdate(): bool
    {
        $user = $this->user();
        $maxAge = $this->config->get('auth.password_max_age_days', 90);

        return $user && method_exists($user, 'passwordNeedsUpdate') && $user->passwordNeedsUpdate($maxAge);
    }

    /**
     * Check if the current guard is token-based.
     */
    private function isTokenGuard(): bool
    {
        $config = $this->config->get("auth.guards.{$this->guard}");

        return ($config['driver'] ?? '') === 'token';
    }

    /**
     * Revoke the current bearer token.
     */
    private function revokeCurrentToken(): void
    {
        $token = $this->request->getBearerToken();

        if ($token && str_contains($token, '|')) {
            [$id, $secret] = explode('|', $token, 2);
            if (is_numeric($id) && $this->token_manager) {
                $this->token_manager->revokeToken((int) $id);
            }
        }
    }
}
