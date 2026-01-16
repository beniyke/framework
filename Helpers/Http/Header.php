<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class provides an easy-to-use interface to manipulate HTTP headers
 * in a standardized way.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http;

class Header
{
    protected $header = [];

    public function __construct(array $header = [])
    {
        foreach ($header as $key => $value) {
            $this->set($key, $value);
        }

        if (! isset($this->header['Content-Type'])) {
            $this->set('Content-Type', 'text/html; charset=UTF-8');
        }

        if (! isset($this->header['Cache-Control'])) {
            $this->set('Cache-Control', 'no-store, max-age=0, no-cache');
        }

        if (! isset($this->header['Date'])) {
            $this->set('Date', date('D, d M Y H:i:s'));
        }
    }

    public function set($key, $value = null): self
    {
        $this->header[$this->formatHeader($key)] = is_string($value) ? trim($value) : $value;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->header[$this->formatHeader($key)] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($this->formatHeader($key), $this->all());
    }

    public function all(): array
    {
        return $this->header;
    }

    public function remove(string $key): void
    {
        if ($this->has($key)) {
            unset($this->header[$key]);
        }
    }

    protected function filterHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $name => $value) {
            if (! is_scalar($value) || ! is_string($name)) {
                continue;
            }
            $name = $this->formatHeader($name);
            $result[$name] = trim($value);
        }

        return $result;
    }

    protected function formatHeader(string $header): string
    {
        if (strtoupper($header) === $header) {
            return $header;
        }

        $words = explode('-', $header);
        $formatted_header = '';

        foreach ($words as $word) {
            $formatted_word = $word;

            if (strtoupper($word) !== $word) {
                $formatted_word = ucfirst(strtolower($word));
            }

            $formatted_header .= $formatted_word . '-';
        }

        return rtrim($formatted_header, '-');
    }
}
