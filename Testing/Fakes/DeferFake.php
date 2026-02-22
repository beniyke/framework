<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fake deferrer for testing deferred task execution.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use Defer\DeferrerInterface;
use PHPUnit\Framework\Assert as PHPUnit;

class DeferFake implements DeferrerInterface
{
    /**
     * All of the payloads that have been pushed.
     *
     * @var array<string, array<int, callable>>
     */
    protected array $payloads = [];

    /**
     * The current named scope.
     */
    protected string $name = 'default';

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function push(callable $payload): void
    {
        $this->payloads[$this->name][] = $payload;
    }

    public function getPayloads(): array
    {
        return $this->payloads[$this->name] ?? [];
    }

    public function hasPayload(): bool
    {
        return ! empty($this->payloads[$this->name]);
    }

    public function clearPayloads(): void
    {
        $this->payloads[$this->name] = [];
    }

    public function all(): array
    {
        return $this->payloads;
    }

    /**
     * Assert that a task was deferred.
     */
    public function assertDeferred(string $name = 'default', ?callable $callback = null): void
    {
        $payloads = $this->payloads[$name] ?? [];

        if ($callback) {
            $matching = array_filter($payloads, $callback);
            PHPUnit::assertTrue(
                count($matching) > 0,
                "The expected task was not deferred in scope [{$name}]."
            );
        } else {
            PHPUnit::assertTrue(
                ! empty($payloads),
                "No tasks were deferred in scope [{$name}]."
            );
        }
    }

    /**
     * Assert that no tasks were deferred.
     */
    public function assertNothingDeferred(): void
    {
        PHPUnit::assertEmpty($this->payloads, 'Tasks were deferred unexpectedly.');
    }
}
