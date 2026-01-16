<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * HasMany relation definition.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Relations;

use Database\Collections\ModelCollection;

class HasMany extends HasOneOrMany
{
    public function getResults(): ModelCollection
    {
        $this->applyConstraints();

        return $this->query->get();
    }

    public function match(array $models, array $results, string $relation): array
    {
        $results = $results instanceof ModelCollection ? $results->all() : $results;

        $dictionary = [];
        $foreignKey = $this->foreignKey;
        $localKey = $this->localKey;

        foreach ($results as $result) {
            $key = (string) $result->{$foreignKey};
            $dictionary[$key][] = $result;
        }

        foreach ($models as $model) {
            $key = (string) $model->{$localKey};
            $model->setRelation($relation, new ModelCollection($dictionary[$key] ?? []));
        }

        return $models;
    }
}
