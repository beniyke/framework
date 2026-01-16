<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * MySQL Query Grammar implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Query;

class MySqlGrammar extends Grammar
{
    public function wrap(string|int $value): string
    {
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

        return '`' . str_replace('`', '``', $value) . '`';
    }

    public function compileLimit(int $limit, ?int $offset): string
    {
        $sql = 'LIMIT ';
        if ($offset > 0) {
            $sql .= $offset . ', ';
        }
        $sql .= $limit;

        return $sql;
    }

    public function compileRandomOrder(): string
    {
        return 'RAND()';
    }

    public function compileSelect(Builder $builder): array
    {
        $sql = $this->compileComponents($builder, $this->selectComponents);

        return [$sql, $builder->getBindings()];
    }

    public function compileAggregate(Builder $builder, string $function, array $columns, string $alias): array
    {
        $rawColumn = reset($columns);
        $column = $rawColumn === '*' ? '*' : $this->wrap($rawColumn);
        $components = $builder->getGroups() ? $this->selectComponents : ['with', 'selects', 'from', 'joins', 'wheres'];

        $originalSelects = $builder->getSelects();
        $rawSql = "{$function}({$column}) AS " . $this->wrap($alias);
        $builder->select([new RawExpression($rawSql)]);
        $sql = $this->compileComponents($builder, $components);
        $builder->select($originalSelects);

        return [$sql, $builder->getBindings()];
    }

    public function compileWhereDate(string $column, string $operator, string $value): array
    {
        $sql = 'DATE(' . $this->wrap($column) . ") {$operator} ?";

        return [$sql, [$value]];
    }

    public function compileWhereTime(string $column, string $operator, string $value): array
    {
        $sql = 'TIME(' . $this->wrap($column) . ") {$operator} ?";

        return [$sql, [$value]];
    }

    public function compileWhereYear(string $column, int $value): array
    {
        $sql = 'YEAR(' . $this->wrap($column) . ') = ?';

        return [$sql, [(string) $value]];
    }

    public function compileWhereDay(string $column, string $operator, string $value): array
    {
        $sql = 'DAYOFMONTH(' . $this->wrap($column) . ") {$operator} ?";

        return [$sql, [$value]];
    }

    public function compileWhereDayBetween(string $column, string $type, int $start, int $end): string
    {
        $wrappedColumn = $this->wrap($column);
        $type = strtoupper($type);

        return "{$type}({$wrappedColumn}) BETWEEN ? AND ?";
    }

    public function compileWhereRegexp(string $column, string $pattern): array
    {
        $sql = $this->wrap($column) . ' REGEXP ?';

        return [$sql, [$pattern]];
    }

    public function compileWhereMatch(array $columns, string $value): array
    {
        $wrappedColumns = array_map([$this, 'wrap'], $columns);
        $columnString = implode(', ', $wrappedColumns);

        $sql = "MATCH ({$columnString}) AGAINST (? IN BOOLEAN MODE)";

        return [$sql, [$value]];
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

        return [str_replace('INSERT INTO', 'INSERT IGNORE INTO', $sql), $bindings];
    }

    public function compileInsertGetId(Builder $builder, array $values, ?string $sequence = null): array
    {
        $columns = array_keys($values);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $wrappedCols = array_map([$this, 'wrap'], $columns);

        $sql = 'INSERT INTO ' . $this->wrap($builder->getTable())
            . ' (' . implode(', ', $wrappedCols) . ') VALUES (' . $placeholders . ')';

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

    protected function compileLock(Builder $builder): string
    {
        if ($builder->getForUpdate()) {
            return 'FOR UPDATE';
        }
        if ($builder->getForSharedLock()) {
            return 'LOCK IN SHARE MODE';
        }

        return '';
    }

    public function compileUpsert(Builder $builder, array $values, array $uniqueBy, array $updateColumns): array
    {
        $columns = array_keys(reset($values));
        [$insertSql, $bindings] = $this->compileInsert($builder, $columns, $values);

        $updateSets = [];
        foreach ($updateColumns as $column) {
            $wrappedColumn = $this->wrap($column);
            $updateSets[] = "{$wrappedColumn} = VALUES({$wrappedColumn})";
        }

        if (empty($updateSets)) {
            $updateSets[] = $this->wrap(reset($uniqueBy)) . ' = ' . $this->wrap(reset($uniqueBy));
        }

        $sql = $insertSql . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateSets);

        return [$sql, $bindings];
    }

    public function compileWhereJsonContains(string $column, string $path, string $type): string
    {
        $jsonPath = $this->wrapJsonPath($path);

        if (str_contains($type, 'NOT')) {
            return "NOT JSON_CONTAINS({$this->wrap($column)}, ?, {$jsonPath})";
        }

        return "JSON_CONTAINS({$this->wrap($column)}, ?, {$jsonPath})";
    }

    public function compileWhereJsonLength(string $column, string $path, string $operator): string
    {
        $jsonPath = $this->wrapJsonPath($path);

        return "JSON_LENGTH({$this->wrap($column)}, {$jsonPath}) {$operator} ?";
    }

    protected function wrapJsonPath(string $path): string
    {
        $path = str_starts_with($path, '$') ? $path : '$.' . $path;

        return "'{$path}'";
    }

    public function compileWhereLast(string $column, int $value, string $unit): string
    {
        $wrappedColumn = $this->wrap($column);
        $unit = strtoupper($unit);

        return "{$wrappedColumn} >= DATE_SUB(NOW(), INTERVAL {$value} {$unit})";
    }

    public function compileTruncate(string $table): string
    {
        return 'TRUNCATE TABLE ' . $this->wrap($table);
    }
}
