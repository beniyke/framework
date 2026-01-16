<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * MorphTo relation definition.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Relations;

use Database\BaseModel;

final class MorphTo extends BaseRelation
{
    public readonly string $typeColumn;

    public readonly string $idColumn;

    public function __construct(BaseModel $parent, string $typeColumn, string $idColumn)
    {
        parent::__construct($parent, get_class($parent));
        $this->typeColumn = $typeColumn;
        $this->idColumn = $idColumn;
    }

    public function getResults(): ?BaseModel
    {
        $type = $this->parent->{$this->typeColumn};
        $id = $this->parent->{$this->idColumn};

        if (!$type || !$id) {
            return null;
        }

        if (!class_exists($type)) {
            return null;
        }

        return $type::find($id);
    }

    protected function applyConstraints(): void
    {
    }

    public function addEagerConstraints(array $models): void
    {
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[get_class($result)][(string) $result->getPrimaryKeyValue()] = $result;
        }

        foreach ($models as $model) {
            $type = $model->{$this->typeColumn};
            $id = (string) $model->{$this->idColumn};

            if (isset($dictionary[$type][$id])) {
                $model->setRelation($relation, $dictionary[$type][$id]);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    public function getMatchKey(): string
    {
        return $this->idColumn;
    }

    public function getForeignKey(): string
    {
        return $this->idColumn;
    }

    public function getLocalKey(): string
    {
        return $this->idColumn;
    }

    public function getQualifiedForeignKeyName(): string
    {
        return $this->parent->getTable() . '.' . $this->idColumn;
    }
}
