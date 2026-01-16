<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * PSR-7 URI implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

final class Uri implements UriInterface
{
    private string $scheme = '';

    private string $user_info = '';

    private string $host = '';

    private ?int $port = null;

    private string $path = '';

    private string $query = '';

    private string $fragment = '';

    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $parts = parse_url($uri);
            if ($parts === false) {
                throw new InvalidArgumentException('Unable to parse URI: ' . $uri);
            }
            $this->applyParts($parts);
        }
    }

    private function applyParts(array $parts): void
    {
        $this->scheme = strtolower($parts['scheme'] ?? '');
        $this->user_info = $parts['user'] ?? '';
        if (isset($parts['pass'])) {
            $this->user_info .= ':' . $parts['pass'];
        }
        $this->host = strtolower($parts['host'] ?? '');
        $this->port = $parts['port'] ?? null;
        $this->path = $this->filterPath($parts['path'] ?? '');
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';
    }

    private function filterPath(string $path): string
    {
        if ($path === '0') {
            return '0';
        }

        return ltrim($path, '/');
    }

    public function __toString(): string
    {
        $uri = '';
        if ($this->scheme !== '') {
            $uri .= $this->scheme . '://';
        }
        if ($this->user_info !== '') {
            $uri .= $this->user_info . '@';
        }
        $uri .= $this->host;
        if ($this->port !== null) {
            $uri .= ':' . $this->port;
        }
        $uri .= '/' . ltrim($this->path, '/');
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        $authority = $this->host;
        if ($this->user_info !== '') {
            $authority = $this->user_info . '@' . $authority;
        }
        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->user_info;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return '/' . $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): self
    {
        $new = clone $this;
        $new->scheme = strtolower($scheme);

        return $new;
    }

    public function withUserInfo(string $user, ?string $password = null): self
    {
        $new = clone $this;
        $new->user_info = $user;
        if ($password !== null) {
            $new->user_info .= ':' . $password;
        }

        return $new;
    }

    public function withHost(string $host): self
    {
        $new = clone $this;
        $new->host = strtolower($host);

        return $new;
    }

    public function withPort(?int $port): self
    {
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException('Invalid port. Must be between 1 and 65535.');
        }
        $new = clone $this;
        $new->port = $port;

        return $new;
    }

    public function withPath(string $path): self
    {
        $new = clone $this;
        $new->path = $this->filterPath($path);

        return $new;
    }

    public function withQuery(string $query): self
    {
        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    public function withFragment(string $fragment): self
    {
        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }
}
