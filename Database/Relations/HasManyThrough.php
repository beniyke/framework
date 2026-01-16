<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * HasManyThrough relation definition.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Relations;

use Database\BaseModel;
use Database\Collections\ModelCollection;
use Database\Query\Builder;

final class HasManyThrough extends HasMany
{
    protected readonly string $firstKey;

    protected readonly string $secondKey;

    protected readonly string $throughLocalKey;

    protected readonly string $throughModel;

    protected readonly string $intermediateTable;

    protected readonly string $intermediateAnchorKey;

    public function __construct(BaseModel $parent, string $relatedModel, string $throughModel, string $firstKey, string $secondKey, string $throughLocalKey, string $relatedLocalKey)
    {
        parent::__construct($parent, $relatedModel, $secondKey, $relatedLocalKey);

        $this->throughModel = $throughModel;
        $this->intermediateTable = (new $throughModel())->getTable();
        $this->firstKey = $firstKey;
        $this->secondKey = $secondKey;
        $this->throughLocalKey = $throughLocalKey;
        $this->intermediateAnchorKey = $this->intermediateTable . '_' . $this->firstKey;

        $this->performJoin();
    }

    protected function performJoin(): void
    {
        $relatedTable = $this->getRelated()->getTable();
        $qualifiedSecondKey = $relatedTable . '.' . $this->secondKey;
        $qualifiedIntermediateLocalKey = $this->intermediateTable . '.' . $this->localKey;

        $this->query->join($this->intermediateTable, $qualifiedSecondKey, '=', $qualifiedIntermediateLocalKey, 'INNER');

        $this->query->select([
            $relatedTable . '.*',
            Builder::raw("{$this->intermediateTable}.{$this->firstKey} AS {$this->intermediateAnchorKey}"),
        ]);
    }

    protected function applyConstraints(): void
    {
        $value = $this->parent->{$this->throughLocalKey};

        if (is_null($value)) {
            $this->query->whereRaw('0 = 1');

            return;
        }

        $this->query->where($this->intermediateTable . '.' . $this->firstKey, '=', $value);
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->throughLocalKey);

        if (empty($keys)) {
            $this->query->whereRaw('0 = 1');

            return;
        }

        $this->query->whereIn($this->intermediateTable . '.' . $this->firstKey, $keys);
    }

    public function match(array $models, array $results, string $relation): array
    {
        $results = $results instanceof ModelCollection ? $results->all() : $results;

        $dictionary = [];
        $anchorKey = $this->intermediateAnchorKey;

        foreach ($results as $result) {
            $key = (string) ($result->{$anchorKey} ?? null);

            if ($key !== null) {
                $dictionary[$key][] = $result;
            }
        }

        foreach ($models as $model) {
            $key = (string) $model->{$this->throughLocalKey};
            $model->setRelation($relation, new ModelCollection($dictionary[$key] ?? []));
        }

        return $models;
    }

    public function getForeignKey(): string
    {
        return $this->firstKey;
    }

    public function getLocalKey(): string
    {
        return $this->throughLocalKey;
    }

    public function getQualifiedForeignKeyName(): string
    {
        return $this->intermediateTable . '.' . $this->firstKey;
    }
}
