<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * SQLite Query Grammar implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Query;

use InvalidArgumentException;

class SqliteGrammar extends Grammar
{
    public function wrap(string|int $value): string
    {
        $value = (string) $value;
        if ($value === '*') {
            return $value;
        }

        if (str_contains($value, '.')) {
            $parts = explode('.', $value);

            return $this->wrap($parts[0]) . '.' . $this->wrap($parts[1]);
        }

        if (str_contains(strtolower($value), ' as ')) {
            $parts = preg_split('/\s+as\s+/i', $value);

            return $this->wrap($parts[0]) . ' AS ' . $this->wrap($parts[1]);
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
        if ($offset !== null) {
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

        $components = ['with', 'selects', 'from', 'joins', 'wheres'];

        $rawSql = "{$function}({$wrappedColumn}) AS " . $this->wrap($alias);
        $builder->select([new RawExpression($rawSql)]);
        $sql = $this->compileComponents($builder, $components);
        $builder->select($originalSelects);

        return [$sql, $builder->getBindings()];
    }

    public function compileWhereDate(string $column, string $operator, string $value): array
    {
        $wrappedColumn = $this->wrap($column);
        $sql = "strftime('%Y-%m-%d', {$wrappedColumn}) {$operator} ?";

        return [$sql, [$value]];
    }

    public function compileWhereTime(string $column, string $operator, string $value): array
    {
        $wrappedColumn = $this->wrap($column);
        $sql = "strftime('%H:%M:%S', {$wrappedColumn}) {$operator} ?";

        return [$sql, [$value]];
    }

    public function compileWhereYear(string $column, int $value): array
    {
        $wrappedColumn = $this->wrap($column);
        $sql = "strftime('%Y', {$wrappedColumn}) = ?";

        return [$sql, [(string) $value]];
    }

    public function compileWhereDay(string $column, string $operator, string $value): array
    {
        $wrappedColumn = $this->wrap($column);
        $sql = "strftime('%d', {$wrappedColumn}) {$operator} ?";

        return [$sql, [$value]];
    }

    public function compileWhereDayBetween(string $column, string $type, int $start, int $end): string
    {
        $wrappedColumn = $this->wrap($column);

        $format = match (strtoupper($type)) {
            'DAYOFWEEK' => '%w',
            'DAYOFMONTH' => '%d',
            'DAYOFYEAR' => '%j',
            default => throw new InvalidArgumentException("Unsupported type for SQLite: {$type}"),
        };

        return "strftime('{$format}', {$wrappedColumn}) BETWEEN ? AND ?";
    }

    public function compileWhereRegexp(string $column, string $pattern): array
    {
        $sql = $this->wrap($column) . ' REGEXP ?';

        return [$sql, [$pattern]];
    }

    public function compileWhereMatch(array $columns, string $value): array
    {
        throw new InvalidArgumentException(
            "SQLite does not support the 'whereMatch' (Full-Text Search) clause without FTS extensions."
        );
    }

    public function compileInsert(Builder $builder, array $columns, array $valueSets): array
    {
        $wrappedCols = array_map(fn (string $c) => $this->wrap($c), $columns);
        $columnString = implode(', ', $wrappedCols);

        $recordPlaceholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $valuePlaceholders = implode(', ', array_fill(0, count($valueSets), $recordPlaceholders));

        $sql = 'INSERT INTO ' . $this->wrap($builder->getTable()) .
            ' (' . $columnString . ') VALUES ' . $valuePlaceholders;

        $bindings = [];
        foreach ($valueSets as $record) {
            $bindings = array_merge($bindings, array_values($record));
        }

        return [$sql, $bindings];
    }

    public function compileInsertOrIgnore(Builder $builder, array $columns, array $valueSets): array
    {
        [$sql, $bindings] = $this->compileInsert($builder, $columns, $valueSets);

        return [str_replace('INSERT INTO', 'INSERT OR IGNORE INTO', $sql), $bindings];
    }

    public function compileInsertGetId(Builder $builder, array $values, ?string $sequence = null): array
    {
        $columns = array_keys($values);
        $wrappedCols = array_map(fn (string $c) => $this->wrap($c), $columns);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $sql = 'INSERT INTO ' . $this->wrap($builder->getTable()) .
            ' (' . implode(', ', $wrappedCols) . ') VALUES (' . $placeholders . ')';

        return [$sql, array_values($values)];
    }

    public function compileUpdate(Builder $builder, array $values): array
    {
        $sets = [];
        $bindings = [];
        foreach ($values as $col => $val) {
            if ($val instanceof RawExpression) {
                $sets[] = $this->wrap($col) . ' = ' . $val->getExpression();
            } else {
                $sets[] = $this->wrap($col) . ' = ?';
                $bindings[] = $val;
            }
        }

        $wheres = $this->compileWheres($builder);

        $sql = 'UPDATE ' . $this->wrap($builder->getTable()) .
            ' SET ' . implode(', ', $sets) . ' ' . $wheres;

        $bindings = array_merge($bindings, $builder->getBindings());

        return [$sql, $bindings];
    }

    public function compileDelete(Builder $builder): array
    {
        $wheres = $this->compileWheres($builder);

        $sql = 'DELETE FROM ' . $this->wrap($builder->getTable()) .
            ' ' . $wheres;

        return [$sql, $builder->getBindings()];
    }

    protected function compileLock(Builder $builder): string
    {
        return '';
    }

    public function compileUpsert(Builder $builder, array $values, array $uniqueBy, array $updateColumns): array
    {
        $columns = array_keys(reset($values));
        [$insertSql, $bindings] = $this->compileInsert($builder, $columns, $values);

        $uniqueCols = array_map([$this, 'wrap'], $uniqueBy);
        $setClauses = [];

        foreach ($updateColumns as $column) {
            $setClauses[] = $this->wrap($column) . ' = excluded.' . $this->wrap($column);
        }

        if (empty($setClauses)) {
            $setClauses[] = $this->wrap(reset($uniqueBy)) . ' = ' . $this->wrap(reset($uniqueBy));
        }

        $sql = $insertSql . ' ON CONFLICT (' . implode(', ', $uniqueCols) . ') DO UPDATE SET ' . implode(', ', $setClauses);

        return [$sql, $bindings];
    }

    public function compileWhereJsonContains(string $column, string $path, string $type): string
    {
        $wrappedColumn = $this->wrap($column);
        $jsonPath = $this->wrapJsonPath($path);

        $operator = str_contains($type, 'NOT') ? '<>' : '=';

        if ($jsonPath === "'$'") {
            $sql = "{$wrappedColumn} {$operator} ?";
        } else {
            $sql = "JSON_EXTRACT({$wrappedColumn}, {$jsonPath}) {$operator} ?";
        }

        return $sql;
    }

    public function compileWhereJsonLength(string $column, string $path, string $operator): string
    {
        $wrappedColumn = $this->wrap($column);
        $jsonPath = $this->wrapJsonPath($path);

        return "JSON_ARRAY_LENGTH({$wrappedColumn}, {$jsonPath}) {$operator} ?";
    }

    protected function wrapJsonPath(string $path): string
    {
        $path = str_starts_with($path, '$') ? $path : '$.' . $path;

        return "'{$path}'";
    }

    public function compileWhereLast(string $column, int $value, string $unit): string
    {
        $wrappedColumn = $this->wrap($column);
        $unit = strtoupper($unit); // SQLite uses uppercase for units in modifiers usually, though case-insensitive

        return "{$wrappedColumn} >= DATETIME('now', '-{$value} {$unit}')";
    }

    public function compileTruncate(string $table): string
    {
        return 'DELETE FROM ' . $this->wrap($table);
    }
}
