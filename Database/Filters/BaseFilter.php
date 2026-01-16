<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Abstract base class for database query filters.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Filters;

use Database\Query\Builder;

abstract class BaseFilter
{
    protected mixed $value;

    protected ?string $column = null;

    protected ?string $table = null;

    public function __construct(mixed $value, ?string $column = null, ?string $table = null)
    {
        $this->value = $value;
        $this->column = $column;
        $this->table = $table;
    }

    /**
     * Apply the filter constraints to the given query builder instance.
     */
    abstract public function apply(Builder $builder): Builder;

    public function shouldApply(): bool
    {
        if (is_array($this->value)) {
            return count(array_filter($this->value, fn ($v) => ! is_null($v) && $v !== '')) > 0;
        }

        return ! is_null($this->value) && $this->value !== '';
    }
}
