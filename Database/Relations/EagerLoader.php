<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Eager loading handler.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Relations;

use Database\BaseModel;
use Database\Collections\ModelCollection;
use InvalidArgumentException;

final class EagerLoader
{
    /**
     * Eagerly load relations recursively.
     *
     * @param array<BaseModel>             $models
     * @param array<string, callable|null> $relations Map of relation name (or aggregation) to constraint callback.
     *
     * @return array<BaseModel>
     */
    public static function load(array $models, array $relations): array
    {
        if (empty($models) || ! $models[0] instanceof BaseModel) {
            return $models;
        }

        foreach ($relations as $relationName => $constraint) {
            if (str_contains($relationName, '.')) {
                [$first, $rest] = explode('.', $relationName, 2);
                $models = self::load($models, [$first => $constraint]);

                $relatedModels = [];
                foreach ($models as $model) {
                    $relationData = $model->{$first};
                    if (is_array($relationData)) {
                        $relatedModels = array_merge($relatedModels, $relationData);
                    } elseif ($relationData instanceof BaseModel) {
                        $relatedModels[] = $relationData;
                    }
                }

                $relatedModels = array_filter($relatedModels, fn ($m) => $m instanceof BaseModel);

                if (! empty($relatedModels)) {
                    $firstRelatedModel = current($relatedModels);
                    $keyName = method_exists($firstRelatedModel, 'getKeyName') ? $firstRelatedModel->getKeyName() : 'id';

                    $uniqueRelatedModels = [];

                    foreach ($relatedModels as $relatedModel) {
                        $uniqueRelatedModels[$relatedModel->{$keyName}] = $relatedModel;
                    }

                    $relatedModels = array_values($uniqueRelatedModels);
                }

                if (! empty($relatedModels)) {
                    $relatedModels = self::load($relatedModels, [$rest => $constraint]);
                }

                continue;
            }

            if (str_contains($relationName, '@')) {
                $models = self::loadAggregateOrExists($models, $relationName, $constraint);

                continue;
            }

            if (! method_exists($models[0], $relationName)) {
                throw new InvalidArgumentException("Eager loading failed: Relationship '{$relationName}' not defined on model.");
            }

            $relationInstance = $models[0]->{$relationName}();

            if ($relationInstance instanceof MorphTo) {
                $models = self::loadMorphTo($models, $relationName, $relationInstance, $constraint);

                continue;
            }

            if (! $relationInstance instanceof BaseRelation) {
                throw new InvalidArgumentException("Method '{$relationName}' did not return a BaseRelation object.");
            }

            if (is_callable($constraint)) {
                $constraint($relationInstance->getQuery());
            }

            $relationInstance->addEagerConstraints($models);
            $results = $relationInstance->getQuery()->get();
            $models = $relationInstance->match($models, $results, $relationName);
        }

        return $models;
    }

    /**
     * Eagerly load a MorphTo relationship.
     *
     * @param array<BaseModel> $models
     */
    protected static function loadMorphTo(array $models, string $relationName, MorphTo $relation, ?callable $constraint): array
    {
        $types = [];
        foreach ($models as $model) {
            $type = $model->{$relation->typeColumn};
            $id = $model->{$relation->idColumn};
            if ($type && $id) {
                $types[$type][] = $id;
            }
        }

        $allResults = [];
        foreach ($types as $type => $ids) {
            $query = $type::query();
            if ($constraint) {
                $constraint($query);
            }
            $results = $query->whereIn((new $type())->getPrimaryKey(), array_unique($ids))->get();
            $allResults = array_merge($allResults, $results instanceof ModelCollection ? $results->all() : $results);
        }

        return $relation->match($models, $allResults, $relationName);
    }

    /**
     * Handle the loading of aggregate functions (COUNT, SUM, AVG, etc.) and exists checks.
     *
     * * @param array<BaseModel> $models
     * @param string        $load       (e.g., 'posts@COUNT:id|as:posts_count')
     * @param callable|null $constraint Optional callable to constrain the relation query.
     *
     * @return array<BaseModel>
     */
    protected static function loadAggregateOrExists(array $models, string $load, ?callable $constraint): array
    {
        $instance = reset($models);

        $parts = explode('@', $load, 2);
        $relation = $parts[0];
        $type = $parts[1];

        if (! method_exists($instance, $relation)) {
            throw new InvalidArgumentException("Aggregation failed: Relationship '{$relation}' not defined on model.");
        }

        $relationObj = $instance->{$relation}();

        if (! $relationObj instanceof BaseRelation) {
            throw new InvalidArgumentException("Aggregation failed: Method '{$relation}' did not return a BaseRelation object.");
        }

        $parentKeyName = $instance->getKeyName();
        $parentIds = array_filter(array_unique(array_column($models, $parentKeyName)));

        if (empty($parentIds)) {
            return $models;
        }

        $relatedQuery = $relationObj->getQuery();
        $foreignKey = $relationObj->getQualifiedForeignKeyName();

        if (str_starts_with($type, 'COUNT') || str_starts_with($type, 'SUM') || str_starts_with($type, 'AVG') || str_starts_with($type, 'MIN') || str_starts_with($type, 'MAX')) {
            preg_match('/^([A-Z]+)(?::([^|]+))?(?:\|as:([^|]+))?(?:\|constraint)?$/', $type, $m);
            $function = $m[1] ?? 'COUNT';
            $column = $m[2] ?? '*';
            $as = $m[3] ?? null;

            $aggQuery = clone $relatedQuery;

            if ($constraint) {
                $constraint($aggQuery);
            }

            $unqualifiedForeignKey = $relationObj->getForeignKey();

            $expression = $function . "({$aggQuery->getGrammar()->wrap($column)}) as agg_alias";
            $aggQuery->selectRaw("{$unqualifiedForeignKey}, {$expression}")
                ->whereIn($unqualifiedForeignKey, $parentIds)
                ->groupBy($unqualifiedForeignKey);

            $results = $aggQuery->get();
            $values = array_column($results, 'agg_alias', $unqualifiedForeignKey);

            $alias = $as ?? strtolower($function) . '_' . $relation . ($column !== '*' ? "_{$column}" : '');

            foreach ($models as $model) {
                $key = $model->{$parentKeyName};
                $value = $values[$key] ?? ($function === 'COUNT' ? 0 : null);

                if ($function === 'COUNT') {
                    $value = (int) $value;
                } elseif (in_array($function, ['SUM', 'AVG', 'MIN', 'MAX']) && $value !== null) {
                    $value = (float) $value;
                }

                $model->setAttribute($alias, $value);
            }
        } elseif (str_starts_with($type, 'exists')) {
            $as = null;

            if (str_contains($type, '|as:')) {
                $as = trim(str_replace('exists|as:', '', $type));
            }

            $unqualifiedForeignKey = $relationObj->getForeignKey();

            $existsQuery = (clone $relatedQuery)
                ->selectRaw("DISTINCT {$unqualifiedForeignKey}")
                ->whereIn($unqualifiedForeignKey, $parentIds)
                ->get();

            $exists = array_flip(array_column($existsQuery, $unqualifiedForeignKey));
            $alias = $as ?? ('has_' . $relation);

            foreach ($models as $model) {
                $key = $model->{$parentKeyName};
                $model->setAttribute($alias, isset($exists[$key]));
            }
        }

        return $models;
    }
}
