<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Database Query Builder.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Query;

use Closure;
use Database\Collections\ModelCollection;
use Database\ConnectionInterface;
use Database\Pagination\CursorPaginator;
use Database\Pagination\Paginator;
use Database\Traits\SoftDeletes;
use Helpers\DateTimeHelper;
use Helpers\File\Cache;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;

class Builder
{
    protected ConnectionInterface $connection;

    protected Grammar $grammar;

    protected ?string $table = null;

    protected ?string $modelClass = null;

    protected array $wheres = [];

    protected array $selects = ['*'];

    protected array $orders = [];

    protected array $groups = [];

    protected array $havings = [];

    protected array $joins = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    protected array $unions = [];

    protected bool $forSharedLock = false;

    protected array $eagerAggregates = [];

    protected ?string $queryComment = null;

    protected array $ctes = [];

    protected array $bindings = [];

    protected array $eagerLoads = [];

    protected array $appliedGlobalScopes = [];

    protected bool $forUpdate = false;

    protected bool $distinct = false;

    protected ?int $cacheSeconds = null;

    protected array $cacheTags = [];

    protected bool $cacheStale = false;

    public function __construct(ConnectionInterface $connection, Grammar $grammar, string|Builder|null $table = null)
    {
        $this->connection = $connection;
        $this->grammar = $grammar;

        if ($table !== null) {
            $this->from($table);
        }
    }

    public function __call(string $method, array $parameters): mixed
    {
        if (isset($this->modelClass)) {
            $modelClass = $this->modelClass;
            $scopeMethod = 'scope' . ucfirst($method);

            if (method_exists($modelClass, $scopeMethod)) {
                $parametersWithBuilder = array_merge([$this], $parameters);

                return (new $modelClass())->{$scopeMethod}(...$parametersWithBuilder);
            }
        }

        throw new RuntimeException("Method {$method} not found on " . static::class);
    }

    protected function newNestedQuery(): Builder
    {
        $query = new self($this->connection, $this->grammar, $this->table);
        $query->setModelClass($this->modelClass);

        return $query;
    }

    protected function newJoinClause(string $table, string $type): JoinClause
    {
        return new JoinClause($table, $type);
    }

    protected function ensureTableIsSet(): void
    {
        if ($this->table === null) {
            throw new RuntimeException('No table is specified for this query operation. Use the from() method or a factory (DB::table()).');
        }
    }

    public function distinct(): self
    {
        $this->distinct = true;

        return $this;
    }

    public function getDistinct(): bool
    {
        return $this->distinct;
    }

    public function getGrammar(): Grammar
    {
        return $this->grammar;
    }

    public function getTable(): string
    {
        $this->ensureTableIsSet();

        return $this->table;
    }

    public function getSelects(): array
    {
        return $this->selects;
    }

    public function getWheres(): array
    {
        return $this->wheres;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getHavings(): array
    {
        return $this->havings;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function getUnions(): array
    {
        return $this->unions;
    }

    public function getEagerAggregates(): array
    {
        return $this->eagerAggregates;
    }

    public function getCtes(): array
    {
        return $this->ctes;
    }

    public function getQueryComment(): ?string
    {
        return $this->queryComment;
    }

    public function withComment(string $comment): self
    {
        $this->queryComment = $comment;

        return $this;
    }

    public function withoutRecursive(string $name, Builder $query): self
    {
        $this->ctes[] = compact('name', 'query') + ['recursive' => false];
        $this->bindings = array_merge($this->bindings, $query->getBindings());

        return $this;
    }

    public function withRecursive(string $name, Builder $query): self
    {
        $this->ctes[] = compact('name', 'query') + ['recursive' => true];
        $this->bindings = array_merge($this->bindings, $query->getBindings());

        return $this;
    }

    public function setEagerLoads(array $relations): self
    {
        $this->eagerLoads = $relations;

        return $this;
    }

    public function getEagerLoads(): array
    {
        return $this->eagerLoads;
    }

    public function setModelClass(?string $class): self
    {
        $this->modelClass = $class;

        return $this;
    }

    public function withoutSoftDeletes(): self
    {
        return $this->withoutGlobalScope(SoftDeletes::class);
    }

    public function addGlobalScope(string $identifier, callable $callback): self
    {
        if (isset($this->appliedGlobalScopes[$identifier])) {
            return $this;
        }

        $this->appliedGlobalScopes[$identifier] = true;

        $callback($this);

        return $this;
    }

    public function withoutGlobalScope(string $identifier): self
    {
        if (! isset($this->appliedGlobalScopes[$identifier])) {
            return $this;
        }

        $this->wheres = array_filter($this->wheres, function (array $where) use ($identifier): bool {
            return ! (isset($where['tag']) && $where['tag'] === $identifier);
        });

        unset($this->appliedGlobalScopes[$identifier]);

        return $this;
    }

    public function withoutGlobalScopes(array $scopes = []): self
    {
        $scopes = $scopes ?: array_keys($this->appliedGlobalScopes);

        foreach ($scopes as $identifier) {
            $this->withoutGlobalScope($identifier);
        }

        return $this;
    }

    public function lockForUpdate(): self
    {
        $this->forUpdate = true;
        $this->forSharedLock = false;

        return $this;
    }

    public function lockForSharedReading(): self
    {
        $this->forSharedLock = true;
        $this->forUpdate = false;

        return $this;
    }

    public function getForUpdate(): bool
    {
        return $this->forUpdate;
    }

    public function getForSharedLock(): bool
    {
        return $this->forSharedLock;
    }

    protected function withAggregate(string $relation, string $function, ?string $column = null, ?string $alias = null): self
    {
        $alias = $alias ?? $relation . '_' . strtolower($function);

        $this->eagerAggregates[$alias] = [
            'relation' => $relation,
            'function' => $function,
            'column' => $column ?? '*',
            'alias' => $alias,
        ];

        return $this;
    }

    public function withCount(string|array $relations, ?string $alias = null): self
    {
        foreach ((array) $relations as $key => $relation) {
            $relName = is_string($key) ? $key : $relation;
            $customAlias = is_string($key) ? $key : $alias;

            $this->withAggregate($relName, 'COUNT', '*', $customAlias);
        }

        return $this;
    }

    public function withSum(string $relation, string $column, ?string $alias = null): self
    {
        return $this->withAggregate($relation, 'SUM', $column, $alias);
    }

    public function withAvg(string $relation, string $column, ?string $alias = null): self
    {
        return $this->withAggregate($relation, 'AVG', $column, $alias);
    }

    public function withMin(string $relation, string $column, ?string $alias = null): self
    {
        return $this->withAggregate($relation, 'MIN', $column, $alias);
    }

    public function withMax(string $relation, string $column, ?string $alias = null): self
    {
        return $this->withAggregate($relation, 'MAX', $column, $alias);
    }

    public function join(string $table, string|callable $first, string $operator = '=', ?string $second = null, string $type = 'inner'): self
    {
        if (is_callable($first)) {
            $join = $this->newJoinClause($table, $type);
            $first($join);
            $this->joins[] = $join->toArray();
            $this->bindings = array_merge($this->bindings, $join->getBindings());
        } else {
            if ($second === null && func_num_args() === 3) {
                $second = $operator;
                $operator = '=';
            }
            $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        }

        return $this;
    }

    public function leftJoin(string $table, string|callable $first, string $operator = '=', ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string $table, string|callable $first, string $operator = '=', ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function where(string|callable|array $column, mixed $operator = null, mixed $value = null): self
    {
        if (is_array($column)) {
            return $this->whereArray($column, 'AND');
        }

        if ($column instanceof Closure) {
            return $this->whereNested($column, 'AND');
        }

        if (is_callable($column) && func_num_args() === 1) {
            return $this->whereNested($column, 'AND');
        }

        if ($value === null && func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if ($value === null) {
            return $operator === '!=' ? $this->whereNotNull($column) : $this->whereNull($column);
        }

        return $this->addWhere(['column' => $column, 'operator' => $operator, 'value' => $value, 'type' => 'basic'], 'AND');
    }

    public function removeWhere(string $column): self
    {
        $bindingOffset = 0;
        $indexesToRemove = [];
        $bindingsToRemove = [];

        foreach ($this->wheres as $index => $where) {
            $bindingCount = 0;

            if (isset($where['query']) && $where['query'] instanceof Builder) {
                $bindingCount = count($where['query']->getBindings());
            } elseif (isset($where['bindings'])) {
                $bindingCount = count($where['bindings']);
            } elseif (isset($where['values'])) {
                $bindingCount = count($where['values']);
            } elseif (isset($where['value'])) {
                $bindingCount = 1;
            } elseif (isset($where['start']) && isset($where['end'])) {
                $bindingCount = 2;
            }

            if (isset($where['column']) && $where['column'] === $column) {
                $indexesToRemove[] = $index;
                if ($bindingCount > 0) {
                    $bindingsToRemove[] = [$bindingOffset, $bindingCount];
                }
            }

            $bindingOffset += $bindingCount;
        }

        foreach (array_reverse($bindingsToRemove) as [$offset, $length]) {
            array_splice($this->bindings, $offset, $length);
        }

        foreach ($indexesToRemove as $index) {
            unset($this->wheres[$index]);
        }

        $this->wheres = array_values($this->wheres);

        return $this;
    }

    protected function whereArray(array $conditions, string $boolean): self
    {
        return $this->whereNested(function (Builder $query) use ($conditions) {
            foreach ($conditions as $column => $value) {
                if (is_array($value) && count($value) === 2) {
                    $query->where($column, $value[0], $value[1]);
                } else {
                    $query->where($column, '=', $value);
                }
            }
        }, $boolean);
    }

    public function orWhere(string|callable $column, mixed $operator = null, mixed $value = null): self
    {
        if (is_callable($column)) {
            return $this->whereNested($column, 'OR');
        }

        if ($value === null) {
            return $operator === '!=' ? $this->orWhereNotNull($column) : $this->orWhereNull($column);
        }

        return $this->addWhere(['column' => $column, 'operator' => $operator, 'value' => $value, 'type' => 'basic'], 'OR');
    }

    public function whereNotEqual(string $column, mixed $value): self
    {
        return $this->where($column, '!=', $value);
    }

    public function orWhereNotEqual(string $column, mixed $value): self
    {
        return $this->orWhere($column, '!=', $value);
    }

    public function whereLike(string $column, string $value): self
    {
        return $this->addWhere(['column' => $column, 'operator' => 'LIKE', 'value' => $value, 'type' => 'basic'], 'AND');
    }

    public function whereNotLike(string $column, string $value): self
    {
        return $this->addWhere(['column' => $column, 'operator' => 'NOT LIKE', 'value' => $value, 'type' => 'basic'], 'AND');
    }

    public function orWhereLike(string $column, string $value): self
    {
        return $this->orWhere($column, 'LIKE', $value);
    }

    public function orWhereNotLike(string $column, string $value): self
    {
        return $this->orWhere($column, 'NOT LIKE', $value);
    }

    public function whereRegexp(string $column, string $pattern, string $boolean = 'AND'): self
    {
        [$sql, $bindings] = $this->grammar->compileWhereRegexp($column, $pattern);

        return $this->whereRaw($sql, $bindings, $boolean);
    }

    public function whereMatch(string|array $columns, string $value, string $boolean = 'AND'): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        [$sql, $bindings] = $this->grammar->compileWhereMatch($columns, $value);

        return $this->whereRaw($sql, $bindings, $boolean);
    }

    public function whereAnyLike(array $columns, string $value, string $boolean = 'AND'): self
    {
        if (empty($columns)) {
            return $this;
        }

        return $this->whereNested(function (Builder $query) use ($columns, $value) {
            $first = true;

            foreach ($columns as $column) {
                $method = $first ? 'whereLike' : 'orWhereLike';
                $query->$method($column, $value);

                $first = false;
            }
        }, $boolean);
    }

    public function orWhereAnyLike(array $columns, string $value): self
    {
        return $this->whereAnyLike($columns, $value, 'OR');
    }

    public function whereBelongsTo(mixed $modelOrId, ?string $foreignKey = null, string $boolean = 'AND'): self
    {
        if (is_object($modelOrId)) {
            if (method_exists($modelOrId, 'getId')) {
                $value = $modelOrId->getId();
            } elseif (property_exists($modelOrId, 'id')) {
                $value = $modelOrId->id;
            } else {
                throw new InvalidArgumentException("Model object passed to whereBelongsTo must have a 'getId()' method or public 'id' property.");
            }
        } else {
            $value = $modelOrId;
        }

        if ($value === null) {
            throw new InvalidArgumentException('Cannot use whereBelongsTo with a null ID or an un-saved model.');
        }

        if ($foreignKey === null) {
            if (! is_object($modelOrId)) {
                throw new InvalidArgumentException('Foreign key column must be specified when modelOrId is a raw ID value (not a model object).');
            }

            $className = (new ReflectionClass($modelOrId))->getShortName();
            $inferredKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)) . '_id';
            $foreignKey = $inferredKey;
        }

        return $this->addWhere(['column' => $foreignKey, 'operator' => '=', 'value' => $value, 'type' => 'basic'], $boolean);
    }

    public function whereRelation(string $relatedTable, string $foreignKey, string $relatedColumn, mixed $value, string $operator = '=', string $boolean = 'AND'): self
    {
        $currentTable = $this->getTable();

        return $this->whereExists(function (Builder $query) use ($relatedTable, $foreignKey, $relatedColumn, $value, $operator, $currentTable) {
            $query->from($relatedTable);
            $relatedPrimaryKey = 'id';
            $query->whereColumn($relatedTable . '.' . $relatedPrimaryKey, '=', $currentTable . '.' . $foreignKey);
            $query->where($relatedColumn, $operator, $value);
            $query->select($relatedPrimaryKey);
        }, $boolean);
    }

    public function whereColumn(string $firstColumn, string $operator, string $secondColumn, string $boolean = 'AND'): self
    {
        return $this->addWhere([
            'firstColumn' => $firstColumn,
            'operator' => $operator,
            'secondColumn' => $secondColumn,
            'type' => 'column',
        ], $boolean);
    }

    public function orWhereColumn(string $firstColumn, string $operator, string $secondColumn): self
    {
        return $this->whereColumn($firstColumn, $operator, $secondColumn, 'OR');
    }

    public function whereSub(string $column, string $operator, Builder $subquery, string $boolean = 'AND'): self
    {
        return $this->addWhere(['column' => $column, 'operator' => $operator, 'query' => $subquery, 'type' => 'subquery'], $boolean);
    }

    public function orWhereSub(string $column, string $operator, Builder $subquery): self
    {
        return $this->whereSub($column, $operator, $subquery, 'OR');
    }

    public function whereNot(callable $callback): self
    {
        return $this->whereNestedBase($callback, 'NOT', 'AND');
    }

    public function orWhereNot(callable $callback): self
    {
        return $this->whereNestedBase($callback, 'NOT', 'OR');
    }

    protected function whereNested(callable $callback, string $boolean = 'AND'): self
    {
        return $this->whereNestedBase($callback, null, $boolean);
    }

    protected function whereNestedBase(callable $callback, ?string $operator = null, string $boolean = 'AND'): self
    {
        $query = $this->newNestedQuery();
        call_user_func($callback, $query);

        return $this->addWhere(['query' => $query, 'type' => 'nested', 'operator' => $operator], $boolean);
    }

    public function whereIn(string $column, array|Builder|Closure $values): self
    {
        if (is_array($values) && empty($values)) {
            return $this->whereRaw('1 = 0');
        }

        if ($values instanceof Closure) {
            $query = $this->newNestedQuery();
            $values($query);
            $values = $query;
        }

        if ($values instanceof Builder) {
            return $this->addWhere(['column' => $column, 'query' => $values, 'type' => 'in_subquery'], 'AND');
        }

        return $this->addWhere(['column' => $column, 'values' => $values, 'type' => 'in'], 'AND');
    }

    public function whereIntegerInRaw(string $column, array $values, string $boolean = 'AND'): self
    {
        if (empty($values)) {
            return $this->whereRaw('1 = 0', [], $boolean);
        }

        $valueString = $this->buildRawIntegerInString($values);
        $sql = $this->grammar->wrap($column) . ' IN (' . $valueString . ')';

        return $this->whereRaw($sql, [], $boolean);
    }

    protected function buildRawIntegerInString(array $values): string
    {
        $sanitizedValues = array_filter($values, fn ($value) => is_numeric($value) && is_int($value));

        if (empty($sanitizedValues)) {
            return 'NULL';
        }

        return implode(', ', $sanitizedValues);
    }

    public function whereNotIn(string $column, array|Builder|Closure $values): self
    {
        if (is_array($values) && empty($values)) {
            return $this;
        }

        if ($values instanceof Closure) {
            $query = $this->newNestedQuery();
            $values($query);
            $values = $query;
        }

        if ($values instanceof Builder) {
            return $this->addWhere(['column' => $column, 'query' => $values, 'type' => 'not_in_subquery'], 'AND');
        }

        return $this->addWhere(['column' => $column, 'values' => $values, 'type' => 'not_in'], 'AND');
    }

    public function whereNull(string $column): self
    {
        return $this->addWhere(['column' => $column, 'type' => 'null'], 'AND');
    }

    public function whereNotNull(string $column): self
    {
        return $this->addWhere(['column' => $column, 'type' => 'not_null'], 'AND');
    }

    public function orWhereNull(string $column): self
    {
        return $this->addWhere(['column' => $column, 'type' => 'null'], 'OR');
    }

    public function orWhereNotNull(string $column): self
    {
        return $this->addWhere(['column' => $column, 'type' => 'not_null'], 'OR');
    }

    public function whereBetween(string $column, array $values): self
    {
        if (count($values) !== 2) {
            throw new InvalidArgumentException('whereBetween requires an array of exactly two values.');
        }

        return $this->addWhere(['column' => $column, 'values' => $values, 'type' => 'between'], 'AND');
    }

    public function orWhereBetween(string $column, array $values): self
    {
        if (count($values) !== 2) {
            throw new InvalidArgumentException('orWhereBetween requires an array of exactly two values.');
        }

        return $this->addWhere(['column' => $column, 'values' => $values, 'type' => 'between'], 'OR');
    }

    public function whereNotBetween(string $column, array $values): self
    {
        if (count($values) !== 2) {
            throw new InvalidArgumentException('whereNotBetween requires an array of exactly two values.');
        }

        return $this->addWhere(['column' => $column, 'values' => $values, 'type' => 'not_between'], 'AND');
    }

    public function whereDateBetween(string $column, array $dates, string $boolean = 'AND', bool $not = false): self
    {
        if (count($dates) !== 2) {
            throw new InvalidArgumentException('whereDateBetween requires an array of exactly two date strings (start, end).');
        }

        [$start, $end] = array_values($dates);

        $type = $not ? 'not_date_between' : 'date_between';

        return $this->addWhere(['column' => $column, 'start' => $start, 'end' => $end, 'type' => $type], $boolean);
    }

    public function whereGreaterThan(string $column, mixed $value): self
    {
        return $this->where($column, '>', $value);
    }

    public function whereLessThan(string $column, mixed $value): self
    {
        return $this->where($column, '<', $value);
    }

    public function whereGreaterThanOrEqual(string $column, mixed $value): self
    {
        return $this->where($column, '>=', $value);
    }

    public function whereLessThanOrEqual(string $column, mixed $value): self
    {
        return $this->where($column, '<=', $value);
    }

    public function whereBefore(string $column, mixed $date): self
    {
        return $this->whereLessThan($column, $date);
    }

    public function whereAfter(string $column, mixed $date): self
    {
        return $this->whereGreaterThan($column, $date);
    }

    public function olderThan(string $column, mixed $date): self
    {
        return $this->whereBefore($column, $date);
    }

    public function earlierThan(string $column, mixed $date): self
    {
        return $this->whereBefore($column, $date);
    }

    public function newerThan(string $column, mixed $date): self
    {
        return $this->whereAfter($column, $date);
    }

    public function furtherThan(string $column, mixed $date): self
    {
        return $this->whereAfter($column, $date);
    }

    public function laterThan(string $column, mixed $date): self
    {
        return $this->whereAfter($column, $date);
    }

    public function whereOnOrAfter(string $column, mixed $date): self
    {
        return $this->whereGreaterThanOrEqual($column, $date);
    }

    public function whereOnOrBefore(string $column, mixed $date): self
    {
        return $this->whereLessThanOrEqual($column, $date);
    }

    public function since(string $column, mixed $date): self
    {
        return $this->whereOnOrAfter($column, $date);
    }

    public function upTo(string $column, mixed $date): self
    {
        return $this->whereOnOrBefore($column, $date);
    }

    public function whereDate(string $column, string $operator, string $value): self
    {
        return $this->addWhere(['column' => $column, 'operator' => $operator, 'value' => $value, 'type' => 'date'], 'AND');
    }

    public function whereMonth(string $column, string $operator, int $value): self
    {
        return $this->addWhere(['column' => $column, 'operator' => $operator, 'value' => (string) $value, 'type' => 'month'], 'AND');
    }

    public function whereDay(string $column, string $operator, int $value): self
    {
        return $this->addWhere(['column' => $column, 'operator' => $operator, 'value' => (string) $value, 'type' => 'day'], 'AND');
    }

    public function whereTime(string $column, string $operator, string $value): self
    {
        [$sql, $bindings] = $this->grammar->compileWhereTime($column, $operator, $value);

        return $this->whereRaw($sql, $bindings);
    }

    public function whereYear(string $column, int $value): self
    {
        [$sql, $bindings] = $this->grammar->compileWhereYear($column, $value);

        return $this->whereRaw($sql, $bindings);
    }

    public function whereLast(string $unit, int $value, string $column = 'created_at', string $boolean = 'AND'): self
    {
        $sql = $this->grammar->compileWhereLast($column, $value, $unit);

        return $this->whereRaw($sql, [], $boolean);
    }

    public function whereDayBetween(string $column, string $type, int $start, int $end, string $boolean = 'AND'): self
    {
        if (! in_array($type, ['DAYOFWEEK', 'DAYOFMONTH', 'DAYOFYEAR'])) {
            throw new InvalidArgumentException("Type must be 'DAYOFWEEK', 'DAYOFMONTH', or 'DAYOFYEAR'.");
        }

        $sql = $this->grammar->compileWhereDayBetween($column, $type, $start, $end);

        return $this->whereRaw($sql, [$start, $end], $boolean);
    }

    public function whereMonthBetween(string $column, int $start, int $end, string $boolean = 'AND'): self
    {
        if ($start < 1 || $start > 12 || $end < 1 || $end > 12) {
            throw new InvalidArgumentException('Month values must be between 1 and 12.');
        }

        $sql = $this->grammar->compileWhereDayBetween($column, 'MONTHOFYEAR', $start, $end);

        return $this->whereRaw($sql, [$start, $end], $boolean);
    }

    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        return $this->addWhere(['sql' => $sql, 'bindings' => $bindings, 'type' => 'raw'], $boolean);
    }

    public function whereJsonContains(string $column, string $path, mixed $value, string $boolean = 'AND', bool $not = false): self
    {
        $type = $not ? 'NOT JSON_CONTAINS' : 'JSON_CONTAINS';

        $sql = $this->grammar->compileWhereJsonContains($column, $path, $type);

        return $this->addWhere(['sql' => $sql, 'bindings' => [$value], 'type' => 'raw'], $boolean);
    }

    public function whereJsonDoesntContain(string $column, string $path, mixed $value, string $boolean = 'AND'): self
    {
        return $this->whereJsonContains($column, $path, $value, $boolean, true);
    }

    public function whereJsonLength(string $column, string $path, string $operator, int $value, string $boolean = 'AND'): self
    {
        $sql = $this->grammar->compileWhereJsonLength($column, $path, $operator);

        return $this->addWhere(['sql' => $sql, 'bindings' => [$value], 'type' => 'raw'], $boolean);
    }

    protected function addWhere(array $where, string $boolean): self
    {
        $where['boolean'] = $boolean;

        if (! isset($where['tag'])) {
            $where['tag'] = array_key_last($this->appliedGlobalScopes) ?? null;
        }

        if (isset($where['query']) && $where['query'] instanceof Builder) {
            $this->bindings = array_merge($this->bindings, $where['query']->bindings);
        } elseif (isset($where['bindings'])) {
            $this->bindings = array_merge($this->bindings, $where['bindings']);
        } elseif (isset($where['values'])) {
            $this->bindings = array_merge($this->bindings, $where['values']);
        } elseif (isset($where['value'])) {
            $this->bindings[] = $where['value'];
        } elseif (isset($where['start']) && isset($where['end'])) {
            $this->bindings[] = $where['start'];
            $this->bindings[] = $where['end'];
        }

        $this->wheres[] = $where;

        return $this;
    }

    public function whereExists(callable|Builder $callback, string $boolean = 'AND'): self
    {
        return $this->whereExistsBase($callback, 'EXISTS', $boolean);
    }

    public function whereNotExists(callable|Builder $callback, string $boolean = 'AND'): self
    {
        return $this->whereExistsBase($callback, 'NOT EXISTS', $boolean);
    }

    protected function whereExistsBase(callable|Builder $callback, string $type, string $boolean): self
    {
        if ($callback instanceof Builder) {
            $query = $callback;
        } else {
            $query = $this->newNestedQuery();
            call_user_func($callback, $query);
        }

        return $this->addWhere(['query' => $query, 'type' => 'exists', 'operator' => $type], $boolean);
    }

    public function with(string|array $relations): self
    {
        $this->eagerLoads = array_merge($this->eagerLoads, (array) $relations);

        return $this;
    }

    public function from(string|Builder $table): self
    {
        if ($table instanceof Builder) {
            $this->table = '(' . $table->toSql() . ')';
            $this->bindings = array_merge($this->bindings, $table->getBindings());
        } elseif (is_string($table)) {
            $this->table = $table;
        }

        return $this;
    }

    public function pluck(string $column): array
    {
        $this->ensureTableIsSet();
        $builder = clone $this;
        $builder->selects = [$column];
        $sql = $builder->toSql();
        $bindings = $builder->getBindings();
        $results = $this->connection->select($sql, $bindings);

        return array_column($results, $column);
    }

    public function select(string|array|RawExpression $columns): self
    {
        $this->selects = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->selects[] = static::raw($expression);

        if (! empty($bindings)) {
            $this->bindings = array_merge($this->bindings, $bindings);
        }

        return $this;
    }

    public function groupBy(string|array|RawExpression $columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        foreach ($columns as $column) {
            if ($column instanceof RawExpression) {
                $this->bindings = array_merge($this->bindings, $column->getBindings());
            }
            $this->groups[] = $column;
        }

        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        return $this->addHaving(['column' => $column, 'operator' => $operator, 'value' => $value, 'type' => 'basic', 'boolean' => 'AND']);
    }

    public function orHaving(string $column, string $operator, mixed $value): self
    {
        return $this->addHaving(['column' => $column, 'operator' => $operator, 'value' => $value, 'type' => 'basic', 'boolean' => 'OR']);
    }

    public function havingRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        return $this->addHaving(['sql' => $sql, 'bindings' => $bindings, 'type' => 'raw', 'boolean' => $boolean]);
    }

    protected function addHaving(array $having): self
    {
        $this->havings[] = $having;

        if (isset($having['value'])) {
            $this->bindings[] = $having['value'];
        } elseif (isset($having['bindings'])) {
            $this->bindings = array_merge($this->bindings, $having['bindings']);
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction);
        if (! in_array($direction, ['ASC', 'DESC'])) {
            throw new InvalidArgumentException("Order direction must be 'asc' or 'desc'.");
        }
        $this->orders[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    public function inRandomOrder(): self
    {
        $RAW = fn (string $sql): RawExpression => new RawExpression($sql);
        $this->orders[] = ['column' => $RAW($this->grammar->compileRandomOrder()), 'direction' => ''];

        return $this;
    }

    public function limit(int $value): self
    {
        $this->limit = $value;

        return $this;
    }

    public function offset(?int $value): self
    {
        return $this->skip($value);
    }

    public function skip(?int $value): self
    {
        $this->offset = $value;

        return $this;
    }

    public function take(int $value): self
    {
        return $this->limit($value);
    }

    public function when(mixed $condition, callable $callback, ?callable $default = null): self
    {
        if ($condition) {
            return $callback($this, $condition) ?? $this;
        }

        if ($default) {
            return $default($this, $condition) ?? $this;
        }

        return $this;
    }

    public function union(Builder $query): self
    {
        $this->unions[] = [
            'query' => $query,
            'all' => false,
        ];

        $this->bindings = array_merge($this->bindings, $query->getBindings());

        return $this;
    }

    public function unionAll(Builder $query): self
    {
        $this->unions[] = [
            'query' => $query,
            'all' => true,
        ];

        $this->bindings = array_merge($this->bindings, $query->getBindings());

        return $this;
    }

    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $count)->get();

            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    public function truncate(): void
    {
        $sql = $this->grammar->compileTruncate($this->getTable());
        $this->connection->statement($sql);
    }

    public function toSql(): string
    {
        $this->ensureTableIsSet();
        [$sql] = $this->grammar->compileSelect($this);

        return $sql;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function compileHavings(): string
    {
        return $this->grammar->compileHavings($this, $this->havings);
    }

    public function cache(int $seconds = 3600): self
    {
        $this->cacheSeconds = $seconds;

        return $this;
    }

    public function cacheWithStale(int $seconds = 3600): self
    {
        $this->cacheSeconds = $seconds;
        $this->cacheStale = true;

        return $this;
    }

    public function cacheTags(array $tags): self
    {
        $this->cacheTags = $tags;

        return $this;
    }

    public function flushQueryCache(): bool
    {
        return Cache::create('query')->withPath($this->table)->clear();
    }

    protected function getCacheKey(): string
    {
        $components = [
            'sql' => $this->toSql(),
            'bindings' => $this->getBindings(),
        ];

        return 'query_' . md5(json_encode($components));
    }

    protected function executeQuery(): ModelCollection|array
    {
        $this->ensureTableIsSet();
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        $results = $this->connection->select($sql, $bindings);

        if ($this->modelClass) {
            $modelClass = $this->modelClass;
            $models = array_map(fn (array $attributes): object => new $modelClass($attributes, true), $results);

            if (! empty($this->eagerAggregates) && method_exists($modelClass, 'eagerLoadAggregates')) {
                $models = $modelClass::eagerLoadAggregates($models, $this->eagerAggregates);
            }

            if (! empty($this->eagerLoads) && method_exists($modelClass, 'eagerLoadRelations')) {
                $models = $modelClass::eagerLoadRelations($models, $this->eagerLoads);
            }

            return new ModelCollection($models);
        }

        return $results;
    }

    public function get(): ModelCollection|array
    {
        if ($this->cacheSeconds) {
            // Ensure required classes are loaded to prevent __PHP_Incomplete_Class
            if ($this->modelClass) {
                if (! class_exists($this->modelClass, false)) {
                    class_exists($this->modelClass, true);
                }
                // Also ensure ModelCollection is loaded
                if (! class_exists(ModelCollection::class, false)) {
                    class_exists(ModelCollection::class, true);
                }
            }

            $cacheKey = $this->getCacheKey();
            $cache = Cache::create('query')->withPath($this->table);

            if (! empty($this->cacheTags)) {
                $cache = $cache->tags($this->cacheTags);
            }

            $callback = fn () => $this->executeQuery();

            if ($this->cacheStale) {
                return $cache->rememberWithStale($cacheKey, $this->cacheSeconds, $callback);
            } else {
                return $cache->remember($cacheKey, $this->cacheSeconds, $callback);
            }
        }

        return $this->executeQuery();
    }

    public function value(string $column): mixed
    {
        $builder = clone $this;
        $builder->select($column)->limit(1);

        [$sql] = $builder->grammar->compileSelect($builder);
        $bindings = $builder->getBindings();

        $results = $this->connection->select($sql, $bindings);

        if (empty($results)) {
            return null;
        }

        $firstRow = reset($results);

        return $firstRow[$column] ?? null;
    }

    public function rawValue(string $column): mixed
    {
        return $this->value($column);
    }

    public function find(mixed $id, array $columns = ['*']): ?object
    {
        $primaryKey = $this->modelClass ? (new $this->modelClass())->getPrimaryKey() : 'id';

        return $this->where($primaryKey, '=', $id)->first($columns);
    }

    public function first(array $columns = ['*']): ?object
    {
        $collection = $this->select($columns)->limit(1)->get();

        if ($collection instanceof ModelCollection) {
            return $collection->first();
        }

        $result = $collection[0] ?? null;

        return is_array($result) ? (object) $result : $result;
    }

    public function exists(): bool
    {
        $this->ensureTableIsSet();
        $builder = clone $this;

        $builder->selects = [static::raw('1')];
        $builder->orders = [];
        $builder->groups = [];
        $builder->havings = [];

        $builder->limit(1)->offset(null);
        $sql = $builder->toSql();
        $results = $this->connection->select($sql, $builder->getBindings());

        return count($results) > 0;
    }

    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    public function count(string $column = '*', ?string $columnAlias = null): int
    {
        return (int) $this->aggregate('COUNT', [$column], $columnAlias);
    }

    public function max(string $column, ?string $columnAlias = null): mixed
    {
        return $this->aggregate('MAX', [$column], $columnAlias);
    }

    public function min(string $column, ?string $columnAlias = null): mixed
    {
        return $this->aggregate('MIN', [$column], $columnAlias);
    }

    public function sum(string $column, ?string $columnAlias = null): mixed
    {
        return $this->aggregate('SUM', [$column], $columnAlias);
    }

    public function avg(string $column, ?string $columnAlias = null): mixed
    {
        return $this->aggregate('AVG', [$column], $columnAlias);
    }

    protected function aggregate(string $function, array $columns = ['*'], ?string $columnAlias = null): mixed
    {
        $this->ensureTableIsSet();
        $builder = clone $this;

        $builder->orders = [];
        $builder->limit = null;
        $builder->offset = null;
        $builder->groups = [];
        $builder->havings = [];
        $builder->selects = ['*'];

        $alias = $columnAlias ?? 'aggregate';

        [$sql, $bindings] = $this->grammar->compileAggregate($builder, $function, $columns, $alias);

        $results = $this->connection->select($sql, $bindings);

        if (empty($results)) {
            return $function === 'COUNT' ? 0 : null;
        }

        $firstResult = (array) $results[0];
        $value = $firstResult[$alias] ?? null;

        if ($function === 'COUNT') {
            return (int) $value;
        }

        return $value;
    }

    public function insert(array $values): bool
    {
        $this->ensureTableIsSet();
        if (empty($values)) {
            return true;
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        [$sql, $bindings] = $this->grammar->compileInsert($this, array_keys(reset($values)), $values);

        return $this->connection->statement($sql, $bindings);
    }

    public function insertOrIgnore(array $values): int
    {
        $this->ensureTableIsSet();
        if (empty($values)) {
            return 0;
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        [$sql, $bindings] = $this->grammar->compileInsertOrIgnore($this, array_keys(reset($values)), $values);

        return $this->connection->update($sql, $bindings);
    }

    public function insertGetId(array $values): int|string
    {
        $this->ensureTableIsSet();

        [$sql, $bindings] = $this->grammar->compileInsertGetId($this, $values);

        return $this->connection->insertGetId($sql, $bindings);
    }

    public function update(array $values): int
    {
        $this->ensureTableIsSet();

        if ($this->modelClass) {
            $model = new $this->modelClass();

            if (method_exists($model, 'usesTimestamps') && $model->usesTimestamps()) {
                $updatedAtColumn = $model->getUpdatedAtColumn();
                if ($updatedAtColumn && ! isset($values[$updatedAtColumn])) {
                    $values[$updatedAtColumn] = DateTimeHelper::now()->format('Y-m-d H:i:s');
                }
            }

            if (method_exists($model, 'castAttributeOnSet')) {
                $castedValues = [];
                foreach ($values as $key => $value) {
                    if ($value instanceof RawExpression) {
                        $castedValues[$key] = $value;
                    } else {
                        $castedValues[$key] = $model->castAttributeOnSet($key, $value);
                    }
                }

                $values = $castedValues;
            }
        }

        [$sql, $bindings] = $this->grammar->compileUpdate($this, $values);

        $bindings = array_map(function ($binding) {
            if (is_bool($binding)) {
                return $binding ? 1 : 0;
            }

            return $binding;
        }, $bindings);

        return $this->connection->update($sql, $bindings);
    }

    public function delete(): int
    {
        $this->ensureTableIsSet();

        [$sql, $bindings] = $this->grammar->compileDelete($this);

        return $this->connection->delete($sql, $bindings);
    }

    public function restore(): int
    {
        $this->ensureTableIsSet();
        if (! $this->modelClass || ! in_array(SoftDeletes::class, class_uses($this->modelClass))) {
            throw new InvalidArgumentException('Cannot restore without a model class using the SoftDeletes trait.');
        }

        $modelClass = $this->modelClass;
        $deletedAtColumn = $modelClass::SOFT_DELETE_COLUMN;
        $builder = clone $this;

        return $builder->withoutSoftDeletes()->update([
            $deletedAtColumn => null,
        ]);
    }

    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        $this->ensureTableIsSet();
        $value = static::raw($this->grammar->wrap($column) . ' + ' . $amount);
        $values = array_merge([$column => $value], $extra);

        return $this->update($values);
    }

    public function incrementEach(array $values, array $extra = []): int
    {
        $this->ensureTableIsSet();
        $updateValues = $extra;

        foreach ($values as $column => $amount) {
            $amount = (int) $amount;
            $updateValues[$column] = static::raw($this->grammar->wrap($column) . ' + ' . $amount);
        }

        return $this->update($updateValues);
    }

    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        $this->ensureTableIsSet();
        $value = static::raw($this->grammar->wrap($column) . ' - ' . $amount);
        $values = array_merge([$column => $value], $extra);

        return $this->update($values);
    }

    public function decrementEach(array $values, array $extra = []): int
    {
        $this->ensureTableIsSet();
        $updateValues = $extra;

        foreach ($values as $column => $amount) {
            $amount = (int) $amount;
            $updateValues[$column] = static::raw($this->grammar->wrap($column) . ' - ' . $amount);
        }

        return $this->update($updateValues);
    }

    public function isPageValid(int $page, int $perPage): bool
    {
        $page = max(1, $page);
        $total = (clone $this)->selectRaw('COUNT(*)')->count();

        if ($total === 0) {
            return $page === 1;
        }

        $lastPage = (int) ceil($total / $perPage);

        return $page <= $lastPage;
    }

    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        $total = (clone $this)->selectRaw('COUNT(*)')->count();
        $lastPage = (int) ceil($total / $perPage);

        if ($total > 0 && $page > $lastPage) {
            $page = $lastPage;
        }

        if ($total === 0) {
            return new Paginator([], 0, $perPage, 1);
        }

        $offset = max(0, ($page - 1) * $perPage);
        $this->offset($offset)->limit($perPage);

        $items = $this->get();

        if (! $items instanceof ModelCollection) {
            $items = [];
        } else {
            $items = $items->all();
        }

        return new Paginator($items, $total, $perPage, $page);
    }

    public function cursor(mixed $afterId = null, int $perPage = 15, string $cursorColumn = 'id'): CursorPaginator
    {
        $modelClass = $this->modelClass;

        if (! method_exists($modelClass, 'getSortableColumns')) {
            throw new RuntimeException("Model {$modelClass} must implement getSortableColumns.");
        }

        $allowedColumns = $modelClass::getSortableColumns();

        if (! in_array($cursorColumn, $allowedColumns)) {
            throw new InvalidArgumentException(
                "Invalid cursor column: '{$cursorColumn}'. Allowed columns are: " . implode(', ', $allowedColumns)
            );
        }

        $perPage = max(1, $perPage);
        $fetchCount = $perPage + 1;

        $this->limit($fetchCount)
            ->oldest($cursorColumn);

        if ($afterId) {
            $this->whereAfter($cursorColumn, $afterId);
        }

        $items = $this->get();

        if ($items instanceof ModelCollection) {
            $items = $items->all();
        } elseif (! is_array($items)) {
            $items = [];
        }

        if (count($items) > $perPage) {
            array_pop($items);
        }

        return new CursorPaginator($items, $perPage, $cursorColumn, $afterId);
    }

    public static function raw(string $expression): RawExpression
    {
        return new RawExpression($expression);
    }
}
