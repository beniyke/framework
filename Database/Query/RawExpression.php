<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Represents a raw SQL expression.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Query;

use InvalidArgumentException;

final class RawExpression
{
    private string $expression;

    private array $bindings;

    public function __construct(string $expression, array $bindings = [])
    {
        if (trim($expression) === '') {
            throw new InvalidArgumentException('Raw SQL expression cannot be empty.');
        }

        $this->expression = $expression;
        $this->bindings = array_values($bindings);

        $this->validateBindings();
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function __toString(): string
    {
        return $this->expression;
    }

    public function expectedBindingCount(): int
    {
        return substr_count($this->expression, '?');
    }

    public function validateBindings(): void
    {
        $expected = $this->expectedBindingCount();
        $actual = count($this->bindings);

        if ($expected !== $actual) {
            throw new InvalidArgumentException(
                "Raw expression expects {$expected} binding(s), but {$actual} provided: '{$this->expression}'"
            );
        }
    }
}
