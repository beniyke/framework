<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Base class for HasOne and HasMany relations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Relations;

use Database\BaseModel;

abstract class HasOneOrMany extends BaseRelation
{
    protected readonly string $foreignKey;

    protected readonly string $localKey;

    public function __construct(BaseModel $parent, string $relatedModel, string $foreignKey, string $localKey)
    {
        parent::__construct($parent, $relatedModel);
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    public function getMatchKey(): string
    {
        return $this->foreignKey;
    }

    public function getQualifiedForeignKeyName(): string
    {
        return $this->getRelated()->getTable() . '.' . $this->foreignKey;
    }

    protected function applyConstraints(): void
    {
        if (is_null($this->parent->{$this->localKey})) {
            $this->query->whereRaw('0 = 1');

            return;
        }

        $this->query->where(
            $this->getQualifiedForeignKeyName(),
            '=',
            $this->parent->{$this->localKey}
        );
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->localKey);

        if (empty($keys)) {
            $this->query->whereRaw('0 = 1');

            return;
        }

        $this->query->whereIn($this->getQualifiedForeignKeyName(), $keys);
    }

    public function save(BaseModel $model): BaseModel
    {
        $model->{$this->foreignKey} = $this->parent->{$this->localKey};

        $model->save();

        return $model;
    }

    public function create(array $attributes): BaseModel
    {
        $attributes[$this->foreignKey] = $this->parent->{$this->localKey};

        return $this->relatedModel::create($attributes);
    }
}
