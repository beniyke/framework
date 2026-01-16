<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Base grammar class for database query compilation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Query;

use InvalidArgumentException;
use RuntimeException;

abstract class Grammar
{
    protected string $driver;

    protected array $selectComponents = ['with', 'selects', 'from', 'joins', 'wheres', 'groups', 'havings', 'orders', 'limit', 'lock'];

    public function __construct(string $driver)
    {
        $this->driver = $driver;
    }

    abstract public function wrap(string|int $value): string;

    abstract public function compileRandomOrder(): string;

    abstract public function compileLimit(int $limit, ?int $offset): string;

    abstract public function compileSelect(Builder $builder): array;

    abstract public function compileAggregate(Builder $builder, string $function, array $columns, string $alias): array;

    abstract public function compileWhereDate(string $column, string $operator, string $value): array;

    abstract public function compileWhereTime(string $column, string $operator, string $value): array;

    abstract public function compileWhereDay(string $column, string $operator, string $value): array;

    abstract public function compileWhereYear(string $column, int $value): array;

    abstract public function compileInsert(Builder $builder, array $columns, array $valueSets): array;

    abstract public function compileInsertOrIgnore(Builder $builder, array $columns, array $valueSets): array;

    abstract public function compileInsertGetId(Builder $builder, array $values, ?string $sequence = null): array;

    abstract public function compileUpdate(Builder $builder, array $values): array;

    abstract public function compileDelete(Builder $builder): array;

    abstract public function compileWhereRegexp(string $column, string $pattern): array;

    abstract public function compileWhereMatch(array $columns, string $value): array;

    abstract protected function compileLock(Builder $builder): string;

    abstract public function compileUpsert(Builder $builder, array $values, array $uniqueBy, array $updateColumns): array;

    abstract public function compileWhereLast(string $column, int $value, string $unit): string;

    abstract public function compileWhereJsonContains(string $column, string $path, string $type): string;

    abstract public function compileWhereJsonLength(string $column, string $path, string $operator): string;

    abstract public function compileWhereDayBetween(string $column, string $type, int $start, int $end): string;

    abstract public function compileTruncate(string $table): string;

    protected function compileWith(Builder $builder, array $ctes): string
    {
        $sql = [];
        $recursive = false;

        foreach ($ctes as $cte) {
            if ($cte['recursive']) {
                $recursive = true;
            }

            [$cteSql] = $this->compileSelect($cte['query']);
            $sql[] = $this->wrap($cte['name']) . ' AS (' . $cteSql . ')';
        }

        $prefix = $recursive ? 'WITH RECURSIVE ' : 'WITH ';

        return $prefix . implode(', ', $sql);
    }

    protected function compileComponents(Builder $builder, array $components): string
    {
        $sql = '';

        if ($builder->getQueryComment()) {
            $sql .= '-- ' . $builder->getQueryComment() . "\n";
        }

        foreach ($components as $component) {
            $method = 'compile' . ucfirst($component);

            switch ($component) {
                case 'with':
                    if (! empty($builder->getCtes())) {
                        $sql .= ' ' . trim((string) $this->$method($builder, $builder->getCtes()));
                    }

                    continue 2;
                case 'selects':
                    if (! empty($builder->getSelects())) {
                        $sql .= ' ' . trim((string) $this->$method($builder, $builder->getSelects()));
                    }

                    continue 2;
                case 'from':
                    if ($builder->getTable() !== null) {
                        $sql .= ' ' . trim((string) $this->$method($builder, $builder->getTable()));
                    }

                    continue 2;
                case 'lock':
                    if ($builder->getForUpdate() || $builder->getForSharedLock()) {
                        if (method_exists($this, $method)) {
                            $sql .= ' ' . trim((string) $this->$method($builder));
                        }
                    }

                    continue 2;
                case 'limit':
                    if ($builder->getLimit() !== null || $builder->getOffset() !== null) {
                        if (method_exists($this, $method)) {
                            $sql .= ' ' . trim((string) $this->$method($builder->getLimit(), $builder->getOffset()));
                        }
                    }

                    continue 2;
            }

            $data = match ($component) {
                'wheres' => $builder->getWheres(),
                'groups' => $builder->getGroups(),
                'havings' => $builder->getHavings(),
                'orders' => $builder->getOrders(),
                'joins' => $builder->getJoins(),
                default => null,
            };

            if (! empty($data)) {
                if (method_exists($this, $method)) {
                    $sql .= ' ' . trim((string) $this->$method($builder, $data));
                } else {
                    throw new RuntimeException("Missing compilation method: {$method}");
                }
            }
        }

        return trim($sql);
    }

    protected function compileFrom(Builder $builder, string $table): string
    {
        return 'FROM ' . $this->wrap($table);
    }

    protected function compileSelects(Builder $builder, array $selects): string
    {
        $columns = array_map(function ($s) {
            return $s instanceof RawExpression ? (string) $s->getExpression() : $this->wrap($s);
        }, $selects);

        $distinct = $builder->getDistinct() ? 'DISTINCT ' : '';

        return 'SELECT ' . $distinct . implode(', ', $columns);
    }

    protected function compileWhereComponents(Builder $builder): string
    {
        if (empty($builder->getWheres())) {
            return '';
        }

        $sql = [];
        foreach ($builder->getWheres() as $where) {
            $type = ucwords($where['type'], '_');
            $type = str_replace('_', '', $type);
            $method = 'compileWhere' . $type;

            if (! method_exists($this, $method)) {
                throw new InvalidArgumentException("Unsupported where type: {$where['type']}. Tried to call method '{$method}'.");
            }

            $fragment = $this->$method($where);

            if (! empty($sql)) {
                $fragment = " {$where['boolean']} {$fragment}";
            }

            $sql[] = $fragment;
        }

        return trim(implode('', $sql));
    }

    public function compileWheres(Builder $builder): string
    {
        $sql = $this->compileWhereComponents($builder);

        return $sql ? 'WHERE ' . $sql : '';
    }

    protected function compileWhereBasic(array $where): string
    {
        return $this->wrap($where['column']) . " {$where['operator']} ?";
    }

    protected function compileWhereColumn(array $where): string
    {
        return $this->wrap($where['firstColumn']) . " {$where['operator']} " . $this->wrap($where['secondColumn']);
    }

    protected function compileWhereRaw(array $where): string
    {
        return $where['sql'];
    }

    protected function compileWhereNull(array $where): string
    {
        return $this->wrap($where['column']) . ' IS NULL';
    }

    protected function compileWhereNotNull(array $where): string
    {
        return $this->wrap($where['column']) . ' IS NOT NULL';
    }

    protected function compileWhereBetween(array $where): string
    {
        return $this->wrap($where['column']) . ' BETWEEN ? AND ?';
    }

    protected function compileWhereNotBetween(array $where): string
    {
        return $this->wrap($where['column']) . ' NOT BETWEEN ? AND ?';
    }

    protected function compileWhereIn(array $where): string
    {
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));

        return $this->wrap($where['column']) . " IN ({$placeholders})";
    }

    protected function compileWhereNotIn(array $where): string
    {
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));

        return $this->wrap($where['column']) . " NOT IN ({$placeholders})";
    }

    protected function compileWhereSubquery(array $where): string
    {
        [$subquerySql] = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) . " {$where['operator']} ({$subquerySql})";
    }

    protected function compileWhereInSubquery(array $where): string
    {
        [$subquerySql] = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) . " IN ({$subquerySql})";
    }

    protected function compileWhereNotInSubquery(array $where): string
    {
        [$subquerySql] = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) . " NOT IN ({$subquerySql})";
    }

    protected function compileWhereExists(array $where): string
    {
        [$subquerySql] = $this->compileSelect($where['query']);

        return "{$where['operator']} ({$subquerySql})";
    }

    protected function compileWhereNested(array $where): string
    {
        $sql = $this->compileWhereComponents($where['query']);

        return '(' . $sql . ')';
    }

    protected function compileWhereDateBetween(array $where): string
    {
        [$startSql] = $this->compileWhereDate($where['column'], '>=', $where['start']);
        [$endSql] = $this->compileWhereDate($where['column'], '<=', $where['end']);

        return $startSql . ' AND ' . $endSql;
    }

    protected function compileWhereNotDateBetween(array $where): string
    {
        return 'NOT (' . $this->compileWhereDateBetween($where) . ')';
    }

    public function compileHavings(Builder $builder, array $havings): string
    {
        if (empty($havings)) {
            return '';
        }

        $sql = [];
        foreach ($havings as $having) {
            $value = '?';
            $fragment = "{$this->wrap($having['column'])} {$having['operator']} {$value}";

            if ($having['type'] === 'raw') {
                $fragment = $having['sql'];
            }

            if (! empty($sql)) {
                $fragment = " {$having['boolean']} {$fragment}";
            }

            $sql[] = $fragment;
        }

        return 'HAVING ' . trim(implode('', $sql));
    }

    protected function compileOrders(Builder $builder, array $orders): string
    {
        $compiled = [];
        foreach ($orders as $order) {
            $column = $order['column'];
            $direction = strtoupper($order['direction']);

            if ($column instanceof RawExpression) {
                $compiled[] = $column->getExpression() . (! empty($direction) ? ' ' . $direction : '');
            } else {
                $compiled[] = $this->wrap($column) . ' ' . $direction;
            }
        }

        return 'ORDER BY ' . implode(', ', $compiled);
    }

    protected function compileGroups(Builder $builder, array $groups): string
    {
        $columns = array_map(function ($g) {
            return $g instanceof RawExpression ? (string) $g->getExpression() : $this->wrap($g);
        }, $groups);

        return 'GROUP BY ' . implode(', ', $columns);
    }

    protected function compileJoins(Builder $builder, array $joins): string
    {
        $sql = [];

        foreach ($joins as $join) {
            $table = $join['table'];

            if (isset($join['alias'])) {
                $table = $this->wrap($table) . ' AS ' . $this->wrap($join['alias']);
            } elseif ($table instanceof Builder) {
                [$subquerySql] = $this->compileSelect($table);
                $table = '(' . $subquerySql . ') AS ' . $this->wrap($join['name'] ?? 't');
            } else {
                $table = $this->wrap($table);
            }

            $joinSql = strtoupper($join['type']) . ' JOIN ' . $table . ' ON ';

            $conditions = [];

            if (isset($join['conditions'])) {
                foreach ($join['conditions'] as $condition) {
                    $fragment = '';
                    $boolean = ' ' . strtoupper($condition['boolean']) . ' ';

                    switch ($condition['type']) {
                        case 'column':
                            $fragment = $this->wrap($condition['first']) . ' ' . $condition['operator'] . ' ' . $this->wrap($condition['second']);
                            break;

                        case 'basic':
                            $fragment = $this->wrap($condition['first']) . ' ' . $condition['operator'] . ' ?';
                            break;

                        case 'raw':
                            $fragment = $condition['sql'];
                            break;

                        case 'null':
                            $fragment = $this->wrap($condition['column']) . ' IS ' . ($condition['not'] ? 'NOT NULL' : 'NULL');
                            break;

                        case 'in':
                            $operator = $condition['not'] ? 'NOT IN' : 'IN';

                            if ($condition['query'] instanceof Builder) {
                                [$subquerySql] = $this->compileSelect($condition['query']);
                                $fragment = $this->wrap($condition['column']) . " {$operator} ({$subquerySql})";
                            } else {
                                $placeholders = implode(', ', array_fill(0, count($condition['values']), '?'));
                                $fragment = $this->wrap($condition['column']) . " {$operator} ({$placeholders})";
                            }
                            break;

                        case 'nested':
                            $nestedWheresSql = $this->compileWhereComponents($condition['query']);
                            $fragment = '(' . $nestedWheresSql . ')';
                            break;

                        default:
                            throw new RuntimeException("Unsupported join condition type: {$condition['type']}.");
                    }

                    $conditions[] = (empty($conditions) ? '' : $boolean) . $fragment;
                }

                $joinSql .= implode('', $conditions);
            } elseif (isset($join['first'])) {
                $joinSql .= $this->wrap($join['first']) . ' ' . $join['operator'] . ' ' . $this->wrap($join['second']);
            } else {
                throw new InvalidArgumentException('JOIN clause defined without any ON conditions.');
            }

            $sql[] = $joinSql;
        }

        return implode(' ', $sql);
    }
}
