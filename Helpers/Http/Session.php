<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Session provides an easy-to-use interface for working with PHP sessions,
 * ensuring security best practices like ID regeneration are followed.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http;

class Session
{
    private const REGENERATE_INTERVAL = 300; // 5 minutes

    private readonly Cookie $cookie;

    public function __construct(Cookie $cookie)
    {
        $this->cookie = $cookie;
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function regenerateId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && ! headers_sent()) {
            session_regenerate_id(true);
            $_SESSION['last_regenerate_time'] = time();
        }
    }

    public function periodicRegenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && ! headers_sent()) {
            $lastRegeneration = $_SESSION['last_regenerate_time'] ?? 0;

            if ($lastRegeneration < time() - self::REGENERATE_INTERVAL) {
                $this->regenerateId();
            }
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function setMultiple(string $identity, array $data): void
    {
        $this->start();
        $_SESSION[$identity] = array_merge($_SESSION[$identity] ?? [], $data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function delete(string|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    public function flush(): void
    {
        $_SESSION = [];
    }

    public function clearAllExcept(array $excludedKeys = []): void
    {
        foreach (array_keys($_SESSION) as $key) {
            if (! in_array($key, $excludedKeys)) {
                unset($_SESSION[$key]);
            }
        }
    }

    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
            $_SESSION = [];

            $this->cookie->forget(session_name(), '/');
        }
    }

    public function getId(): string
    {
        return session_id();
    }

    public function all(): array
    {
        return $_SESSION ?? [];
    }
}
