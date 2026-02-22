<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fake session for testing session operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use Helpers\Http\Cookie;
use Helpers\Http\Session;
use Mockery;
use PHPUnit\Framework\Assert as PHPUnit;

class SessionFake extends Session
{
    /**
     * The in-memory session data.
     */
    protected array $session = [];

    /**
     * The session ID.
     */
    protected string $id = 'fake-session-id';

    public function __construct()
    {
        // We don't need a real cookie helper for the fake session
        // but we might need a mock if the base class uses it in destroy()
        parent::__construct(Mockery::mock(Cookie::class));
    }

    public function start(): void
    {
        // Do nothing in fake
    }

    public function regenerateId(): void
    {
        $this->id = 'regenerated-' . uniqid();
    }

    public function periodicRegenerate(): void
    {
        // Do nothing in fake
    }

    public function set(string $key, mixed $value): void
    {
        $this->session[$key] = $value;
    }

    public function setMultiple(string $identity, array $data): void
    {
        $this->session[$identity] = array_merge($this->session[$identity] ?? [], $data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->session[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->session[$key]);
    }

    public function delete(string|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        foreach ($keys as $key) {
            unset($this->session[$key]);
        }
    }

    public function flush(): void
    {
        $this->session = [];
    }

    public function clearAllExcept(array $excludedKeys = []): void
    {
        foreach (array_keys($this->session) as $key) {
            if (! in_array($key, $excludedKeys)) {
                unset($this->session[$key]);
            }
        }
    }

    public function destroy(): void
    {
        $this->session = [];
        $this->id = '';
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function all(): array
    {
        return $this->session;
    }

    /**
     * Assert that the session has a given key.
     */
    public function assertHas(string $key, mixed $value = null): void
    {
        PHPUnit::assertTrue(
            $this->has($key),
            "Session is missing expected key [{$key}]."
        );

        if ($value !== null) {
            PHPUnit::assertEquals(
                $value,
                $this->get($key),
                "Session key [{$key}] does not have expected value."
            );
        }
    }

    /**
     * Assert that the session is missing a given key.
     */
    public function assertMissing(string $key): void
    {
        PHPUnit::assertFalse(
            $this->has($key),
            "Session unexpectedly has key [{$key}]."
        );
    }
}
