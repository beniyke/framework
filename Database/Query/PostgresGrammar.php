<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * PostgreSQL Query Grammar implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Query;

use InvalidArgumentException;

class PostgresGrammar extends Grammar
{
    public function wrap(string|int $value): string
    {
        if ($value === '*') {
            return $value;
        }

        if (str_contains(strtolower($value), ' as ')) {
            $parts = preg_split('/\s+as\s+/i', $value);

            return $this->wrap($parts[0]) . ' AS ' . $this->wrap($parts[1]);
        }

        if (str_contains($value, '.')) {
            return '"' . str_replace('.', '"."', $value) . '"';
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    public function compileRandomOrder(): string
    {
        return 'RANDOM()';
    }

    public function compileLimit(int $limit, ?int $offset): string
    {
        $sql = 'LIMIT ' . $limit;
        if ($offset !== null && $offset > 0) {
            $sql .= ' OFFSET ' . $offset;
        }

        return $sql;
    }

    public function compileSelect(Builder $builder): array
    {
        $sql = $this->compileComponents($builder, $this->selectComponents);

        return [$sql, $builder->getBindings()];
    }

    public function compileAggregate(Builder $builder, string $function, array $columns, string $alias): array
    {
        $column = reset($columns);
        $wrappedColumn = $column === '*' ? '*' : $this->wrap($column);
        $originalSelects = $builder->getSelects();
        $components = $builder->getGroups() ? $this->selectComponents : ['with', 'selects', 'from', 'joins', 'wheres'];

        $rawSql = "{$function}({$wrappedColumn}) AS " . $this->wrap($alias);
        $builder->select([new RawExpression($rawSql)]);
        $sql = $this->compileComponents($builder, $components);
        $builder->select($originalSelects);

        return [$sql, $builder->getBindings()];
    }

    public function compileWhereDate(string $column, string $operator, string $value): array
    {
        $sql = "CAST({$this->wrap($column)} AS DATE) {$operator} ?";

        return [$sql, [$value]];
    }

    public function compileWhereTime(string $column, string $operator, string $value): array
    {
        $sql = "CAST({$this->wrap($column)} AS TIME) {$operator} ?";

        return [$sql, [$value]];
    }

    public function compileWhereDay(string $column, string $operator, string $value): array
    {
        $sql = "EXTRACT(DAY FROM {$this->wrap($column)}) {$operator} ?";

        return [$sql, [(string) $value]];
    }

    public function compileWhereYear(string $column, int $value): array
    {
        $sql = "EXTRACT(YEAR FROM {$this->wrap($column)}) = ?";

        return [$sql, [(string) $value]];
    }

    public function compileWhereDayBetween(string $column, string $type, int $start, int $end): string
    {
        $wrappedColumn = $this->wrap($column);
        $type = strtoupper($type);

        $datePart = match ($type) {
            'DAYOFWEEK' => 'DOW',
            'DAYOFMONTH' => 'DAY',
            'DAYOFYEAR' => 'DOY',
            default => throw new InvalidArgumentException("Unsupported day part type for PostgreSQL: {$type}"),
        };

        return "EXTRACT({$datePart} FROM {$wrappedColumn}) BETWEEN ? AND ?";
    }

    public function compileWhereRegexp(string $column, string $pattern): array
    {
        $sql = $this->wrap($column) . ' ~ ?';

        return [$sql, [$pattern]];
    }

    public function compileWhereMatch(array $columns, string $value): array
    {
        $wrappedColumns = array_map(fn ($c) => "to_tsvector('simple', {$this->wrap($c)})", $columns);
        $columnString = implode(' || ', $wrappedColumns);
        $sql = "({$columnString}) @@ to_tsquery('simple', ?)";

        return [$sql, [$value]];
    }

    public function compileWhereJsonContains(string $column, string $path, string $type): string
    {
        $wrappedColumn = $this->wrap($column);

        if (str_contains($type, 'NOT')) {
            return "NOT ({$wrappedColumn}->{$this->wrapJsonPath($path)} @> ?)";
        }

        return "{$wrappedColumn} @> CAST(? AS JSONB)";
    }

    public function compileWhereJsonLength(string $column, string $path, string $operator): string
    {
        $wrappedColumn = $this->wrap($column);

        return "JSONB_ARRAY_LENGTH({$wrappedColumn} -> {$this->wrapJsonPath($path)}) {$operator} ?";
    }

    protected function wrapJsonPath(string $path): string
    {
        return "'{$path}'";
    }

    public function compileWhereLast(string $column, int $value, string $unit): string
    {
        $wrappedColumn = $this->wrap($column);
        $unit = strtolower($unit);

        return "{$wrappedColumn} >= NOW() - INTERVAL '{$value} {$unit}'";
    }

    public function compileInsert(Builder $builder, array $columns, array $valueSets): array
    {
        $wrappedCols = array_map([$this, 'wrap'], $columns);
        $columnString = implode(', ', $wrappedCols);

        $recordPlaceholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $valuePlaceholders = implode(', ', array_fill(0, count($valueSets), $recordPlaceholders));

        $bindings = [];
        foreach ($valueSets as $record) {
            $bindings = array_merge($bindings, array_values($record));
        }

        $sql = 'INSERT INTO ' . $this->wrap($builder->getTable())
            . ' (' . $columnString . ') VALUES ' . $valuePlaceholders;

        return [$sql, $bindings];
    }

    public function compileInsertOrIgnore(Builder $builder, array $columns, array $valueSets): array
    {
        [$sql, $bindings] = $this->compileInsert($builder, $columns, $valueSets);

        return [$sql . ' ON CONFLICT DO NOTHING', $bindings];
    }

    public function compileInsertGetId(Builder $builder, array $values, ?string $sequence = null): array
    {
        $columns = array_keys($values);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $wrappedCols = array_map([$this, 'wrap'], $columns);
        $returning = $this->wrap($sequence ?: 'id');

        $sql = 'INSERT INTO ' . $this->wrap($builder->getTable())
            . ' (' . implode(', ', $wrappedCols) . ') VALUES (' . $placeholders . ') RETURNING ' . $returning;

        return [$sql, array_values($values)];
    }

    public function compileUpdate(Builder $builder, array $values): array
    {
        $sets = [];
        $valueBindings = [];
        foreach ($values as $col => $val) {
            if ($val instanceof RawExpression) {
                $sets[] = $this->wrap($col) . ' = ' . $val->getExpression();
            } else {
                $sets[] = $this->wrap($col) . ' = ?';
                $valueBindings[] = $val;
            }
        }

        $sql = 'UPDATE ' . $this->wrap($builder->getTable())
            . ' SET ' . implode(', ', $sets)
            . ' ' . $this->compileWheres($builder);

        $bindings = array_merge($valueBindings, $builder->getBindings());

        return [$sql, $bindings];
    }

    public function compileDelete(Builder $builder): array
    {
        $sql = 'DELETE FROM ' . $this->wrap($builder->getTable())
            . ' ' . $this->compileWheres($builder);

        return [$sql, $builder->getBindings()];
    }

    public function compileUpsert(Builder $builder, array $values, array $uniqueBy, array $updateColumns): array
    {
        $columns = array_keys(reset($values));
        [$insertSql, $bindings] = $this->compileInsert($builder, $columns, $values);

        $uniqueCols = array_map([$this, 'wrap'], $uniqueBy);
        $setClauses = [];

        foreach ($updateColumns as $column) {
            $wrappedColumn = $this->wrap($column);
            $setClauses[] = "{$wrappedColumn} = EXCLUDED.{$wrappedColumn}";
        }

        if (empty($setClauses)) {
            $wrappedUniqueCol = $this->wrap(reset($uniqueBy));
            $setClauses[] = "{$wrappedUniqueCol} = EXCLUDED.{$wrappedUniqueCol}";
        }

        $sql = $insertSql . ' ON CONFLICT (' . implode(', ', $uniqueCols) . ') DO UPDATE SET ' . implode(', ', $setClauses);

        return [$sql, $bindings];
    }

    protected function compileLock(Builder $builder): string
    {
        if ($builder->getForUpdate()) {
            return 'FOR UPDATE';
        }
        if ($builder->getForSharedLock()) {
            return 'FOR SHARE';
        }

        return '';
    }

    public function compileTruncate(string $table): string
    {
        return 'TRUNCATE ' . $this->wrap($table) . ' RESTART IDENTITY CASCADE';
    }
}
