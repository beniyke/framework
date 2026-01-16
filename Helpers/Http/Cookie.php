<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Cookie management helper.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http;

use DateTimeInterface;
use InvalidArgumentException;

use function setcookie;

class Cookie
{
    private const DEFAULT_SAMESITE = 'Lax';

    public static function configureSessionCookie(int $lifetime, string $path = '/', ?string $domain = null, bool $secure = true, bool $httpOnly = true, string $sameSite = self::DEFAULT_SAMESITE): void
    {
        if (! in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new InvalidArgumentException("SameSite attribute must be 'Lax', 'Strict', or 'None'.");
        }

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ]);
    }

    public function set(string $name, string $value = '', int|DateTimeInterface $expiry = 0, string $path = '/', ?string $domain = null, bool $secure = true, bool $httpOnly = true, string $sameSite = self::DEFAULT_SAMESITE): bool
    {
        if (! in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new InvalidArgumentException("SameSite attribute must be 'Lax', 'Strict', or 'None'.");
        }

        $expires = $this->getExpiryTimestamp($expiry);

        $options = [
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];

        if ($sameSite === 'None' && $secure === false) {
            throw new InvalidArgumentException('SameSite=None requires Secure=true.');
        }

        return setcookie($name, $value, $options);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $_COOKIE[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    public function forget(string $name, string $path = '/', ?string $domain = null): bool
    {
        return $this->set($name, '', 1, $path, $domain);
    }

    public function delete(string $name, string $path = '/', ?string $domain = null): bool
    {
        return $this->forget($name, $path, $domain);
    }

    protected function getExpiryTimestamp(int|DateTimeInterface $expiry): int
    {
        if ($expiry instanceof DateTimeInterface) {
            return $expiry->getTimestamp();
        }

        if ($expiry === 0) {
            return 0;
        }

        return time() + $expiry;
    }
}
