<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * MorphMany relation definition.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Relations;

use Database\BaseModel;

class MorphMany extends HasMany
{
    protected readonly string $morphType;

    protected readonly string $morphClass;

    public function __construct(BaseModel $parent, string $relatedModel, string $foreignKey, string $localKey, string $morphType, string $morphClass)
    {
        parent::__construct($parent, $relatedModel, $foreignKey, $localKey);
        $this->morphType = $morphType;
        $this->morphClass = $morphClass;
        $this->applyConstraints();
    }

    protected function applyConstraints(): void
    {
        parent::applyConstraints();
        $this->query->where($this->getQualifiedMorphType(), '=', $this->morphClass);
    }

    public function addEagerConstraints(array $models): void
    {
        parent::addEagerConstraints($models);
        $this->query->where($this->getQualifiedMorphType(), '=', $this->morphClass);
    }

    public function getQualifiedMorphType(): string
    {
        return $this->getRelated()->getTable() . '.' . $this->morphType;
    }

    public function save(BaseModel $model): BaseModel
    {
        $model->{$this->morphType} = $this->morphClass;

        return parent::save($model);
    }

    public function create(array $attributes): BaseModel
    {
        $attributes[$this->morphType] = $this->morphClass;

        return parent::create($attributes);
    }
}
