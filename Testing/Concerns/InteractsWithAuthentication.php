<?php

declare(strict_types=1);

namespace Testing\Concerns;

use Database\BaseModel;
use Helpers\Http\Session;
use PHPUnit\Framework\Assert;

trait InteractsWithAuthentication
{
    /**
     * Set the currently authenticated user for the application.
     */
    protected function actingAs(BaseModel $user, ?string $connection = null): self
    {
        $this->setSession('user_id', $user->id);
        $this->setSession('authenticated', true);

        return $this;
    }

    /**
     * Assert that the user is authenticated.
     */
    protected function assertAuthenticated(?string $connection = null): self
    {
        Assert::assertTrue(
            $this->getSession('authenticated') === true,
            'The user is not authenticated.'
        );

        return $this;
    }

    /**
     * Assert that the user is not authenticated.
     */
    protected function assertGuest(?string $connection = null): self
    {
        Assert::assertFalse(
            $this->getSession('authenticated') === true,
            'The user is unexpectedly authenticated.'
        );

        return $this;
    }

    /**
     * Set session data for testing.
     */
    protected function setSession(string $key, mixed $value): void
    {
        $session = resolve(Session::class);
        $session->set($key, $value);
    }

    protected function getSession(string $key): mixed
    {
        $session = resolve(Session::class);

        return $session->get($key);
    }
}
