<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * HasOne relation definition.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Relations;

use Database\BaseModel;
use Database\Collections\ModelCollection;
use Database\Query\Builder;
use InvalidArgumentException;

class HasOne extends HasOneOrMany
{
    protected bool $isOfMany = false;

    protected ?string $ofManyColumn = null;

    protected ?string $ofManyAggregate = null;

    public function isOfMany(): bool
    {
        return $this->isOfMany;
    }

    public function getResults(): ?BaseModel
    {
        $this->applyConstraints();

        return $this->query->first();
    }

    public function match(array $models, array $results, string $relation): array
    {
        $results = $results instanceof ModelCollection ? $results->all() : $results;

        $dictionary = [];
        $foreignKey = $this->foreignKey;
        $localKey = $this->localKey;

        foreach ($results as $result) {
            $key = (string) $result->{$foreignKey};
            $dictionary[$key] = $result;
        }

        foreach ($models as $model) {
            $key = (string) $model->{$localKey};
            $model->setRelation($relation, $dictionary[$key] ?? null);
        }

        return $models;
    }

    public function latestOfMany(string $column = 'id'): self
    {
        return $this->ofMany($column, 'MAX');
    }

    public function oldestOfMany(string $column = 'id'): self
    {
        return $this->ofMany($column, 'MIN');
    }

    public function ofMany(string $column, string $aggregate): self
    {
        $aggregate = strtoupper($aggregate);

        if (! in_array($aggregate, ['MIN', 'MAX'])) {
            throw new InvalidArgumentException('Aggregate must be "MIN" or "MAX".');
        }

        $this->isOfMany = true;
        $this->ofManyColumn = $column;
        $this->ofManyAggregate = $aggregate;

        $direction = ($aggregate === 'MIN') ? 'asc' : 'desc';
        $this->query->orderBy($this->getQualifiedRelatedColumn($column), $direction)->limit(1);

        return $this;
    }

    private function getQualifiedRelatedColumn(string $column): string
    {
        return $this->getRelated()->getTable() . '.' . $column;
    }

    public function applyOfManyEagerConstraint(Builder $query, array $models): void
    {
        $relatedTable = $this->getRelated()->getTable();
        $foreignKey = $this->foreignKey;
        $ofManyColumn = $this->ofManyColumn;

        $subquery = $this->newQuery();

        $subquery->select([$subquery->raw($this->ofManyAggregate . '(' . $ofManyColumn . ') AS ' . $ofManyColumn), $foreignKey])
            ->whereIn($foreignKey, $this->getKeys($models, $this->localKey))
            ->groupBy($foreignKey);

        $query->joinOfMany($subquery, $relatedTable, $foreignKey, $ofManyColumn);

        $query->select($relatedTable . '.*');
    }
}
