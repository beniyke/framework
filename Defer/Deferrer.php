<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Deferrer is designed to store and manage a collection of payloads,
 * which are tasks that can be deferred for later use.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Defer;

class Deferrer implements DeferrerInterface
{
    private array $payloads = [];

    private string $current_name = 'default';

    public function __construct()
    {
    }

    public function name(string $name): self
    {
        $this->current_name = $name;

        return $this;
    }

    public function push(callable $payload): void
    {
        if (! isset($this->payloads[$this->current_name])) {
            $this->payloads[$this->current_name] = [];
        }

        $this->payloads[$this->current_name][] = $payload;
    }

    public function getPayloads(): array
    {
        return $this->payloads[$this->current_name] ?? [];
    }

    public function hasPayload(): bool
    {
        return ! empty($this->payloads[$this->current_name]);
    }

    public function clearPayloads(): void
    {
        $this->payloads[$this->current_name] = [];
    }
}
