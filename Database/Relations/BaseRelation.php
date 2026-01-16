<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Abstract base class for Eloquent relations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Relations;

use Database\BaseModel;
use Database\Collections\ModelCollection;
use Database\Query\Builder;
use RuntimeException;

/**
 * @mixin Builder
 *
 * @method $this orderBy(string $column, string $direction = 'asc')
 * @method $this where(string|Closure|array $column, mixed $operator = null, mixed $value = null)
 * @method $this whereIn(string $column, mixed $values)
 * @method $this with(array|string $relations)
 * @method $this select(array|mixed $columns = ['*'])
 */
abstract class BaseRelation
{
    protected readonly BaseModel $parent;

    protected readonly string $relatedModel;

    public readonly Builder $query;

    public function __construct(BaseModel $parent, string $relatedModel)
    {
        $this->parent = $parent;
        $this->relatedModel = $relatedModel;
        $this->query = $relatedModel::query();
    }

    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->query, $method)) {
            $result = $this->query->{$method}(...$parameters);

            if ($result instanceof Builder) {
                return $this;
            }

            return $result;
        }

        throw new RuntimeException("Method {$method} not found on " . static::class);
    }

    public function get(): mixed
    {
        $this->applyConstraints();

        return $this->getResults();
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }

    public function getRelated(): BaseModel
    {
        return new $this->relatedModel();
    }

    public function getRelatedTable(): string
    {
        return $this->getRelated()->getTable();
    }

    abstract public function getMatchKey(): string;

    public function newQuery(): Builder
    {
        return $this->getRelated()::query();
    }

    abstract public function getForeignKey(): string;

    abstract public function getLocalKey(): string;

    abstract public function getQualifiedForeignKeyName(): string;

    abstract public function getResults(): mixed;

    abstract protected function applyConstraints(): void;

    abstract public function addEagerConstraints(array $models): void;

    /**
     * @param array<int, BaseModel>                 $models   Parent models
     * @param array<int, BaseModel>|ModelCollection $results  Eagerly loaded related results
     * @param string                                $relation Name of the relation
     *
     * @return array<int, BaseModel>
     */
    abstract public function match(array $models, array $results, string $relation): array;

    public function getExistenceQuery(): Builder
    {
        return clone $this->query;
    }

    protected function getKeys(array $models, ?string $key = null): array
    {
        $keys = [];
        $key = $key ?? $this->parent->getPrimaryKey();

        foreach ($models as $model) {
            if (! is_null($value = $model->{$key})) {
                $keys[] = $value;
            }
        }

        return array_unique($keys);
    }
}
