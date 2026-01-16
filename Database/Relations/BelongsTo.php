<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * BelongsTo relation definition.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Relations;

use Database\BaseModel;
use Database\Collections\ModelCollection;

final class BelongsTo extends BaseRelation
{
    public readonly string $foreignKey;

    public readonly string $ownerKey;

    protected ?array $defaultAttributes = null;

    public function __construct(BaseModel $parent, string $relatedModel, string $foreignKey, string $ownerKey)
    {
        parent::__construct($parent, $relatedModel);
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        $this->applyConstraints();
    }

    public function withDefault(array $attributes = ['id' => null]): self
    {
        $this->defaultAttributes = $attributes;

        return $this;
    }

    protected function applyConstraints(): void
    {
        $value = $this->parent->{$this->foreignKey};

        if (is_null($value) && $this->defaultAttributes === null) {
            $this->query->whereRaw('0 = 1');

            return;
        }

        if (! is_null($value)) {
            $this->query->where($this->ownerKey, '=', $value);
        }
    }

    public function getResults(): ?BaseModel
    {
        $foreignKeyValue = $this->parent->{$this->foreignKey};

        if (is_null($foreignKeyValue)) {
            return $this->defaultAttributes !== null ? $this->getNewRelatedInstanceWithDefaults() : null;
        }

        $result = $this->query->first();

        if (is_null($result) && $this->defaultAttributes !== null) {
            return $this->getNewRelatedInstanceWithDefaults();
        }

        return $result;
    }

    protected function getNewRelatedInstanceWithDefaults(): BaseModel
    {
        $relatedModelClass = $this->relatedModel;
        $instance = new $relatedModelClass();

        foreach ($this->defaultAttributes as $key => $value) {
            $instance->{$key} = $value;
        }

        if (! isset($this->defaultAttributes[$this->ownerKey])) {
            $instance->{$this->ownerKey} = null;
        }

        return $instance;
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->foreignKey);

        if (empty($keys)) {
            $this->query->whereRaw('0 = 1');

            return;
        }

        $this->query->whereIn($this->ownerKey, $keys);
    }

    public function match(array $models, array $results, string $relation): array
    {
        $results = $results instanceof ModelCollection ? $results->all() : $results;

        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[(string) $result->{$this->ownerKey}] = $result;
        }

        foreach ($models as $model) {
            $foreignKeyValue = $model->{$this->foreignKey};
            $key = (string) $foreignKeyValue;

            $relatedModel = $dictionary[$key] ?? null;

            if (is_null($relatedModel) && $this->defaultAttributes !== null) {
                if (! is_null($foreignKeyValue) || $foreignKeyValue === null) {
                    $relatedModel = $this->getNewRelatedInstanceWithDefaults();
                }
            }

            $model->setRelation($relation, $relatedModel);
        }

        return $models;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getQualifiedForeignKeyName(): string
    {
        return $this->parent->getTable() . '.' . $this->foreignKey;
    }

    public function getLocalKey(): string
    {
        return $this->ownerKey;
    }

    public function getMatchKey(): string
    {
        return $this->ownerKey;
    }
}
