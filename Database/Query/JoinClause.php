<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Represents a JOIN clause in a database query.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Query;

class JoinClause
{
    protected string $table;

    protected string $type;

    protected array $wheres = [];

    protected array $bindings = [];

    public function __construct(string $table, string $type)
    {
        $this->table = $table;
        $this->type = $type;
    }

    public function on(string $first, string $operator, string $second, string $boolean = 'AND'): self
    {
        $this->wheres[] = compact('first', 'operator', 'second', 'boolean');

        return $this;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'table' => $this->table,
            'conditions' => $this->wheres,
        ];
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }
}
