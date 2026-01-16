<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Deferrer Interface defines the contract for deferring tasks.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Defer;

interface DeferrerInterface
{
    public function name(string $name): self;

    public function push(callable $payload): void;

    public function getPayloads(): array;

    public function hasPayload(): bool;

    public function clearPayloads(): void;
}
