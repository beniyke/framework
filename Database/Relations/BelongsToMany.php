<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * BelongsToMany relation definition.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Relations;

use Database\BaseModel;
use Database\Collections\ModelCollection;
use Database\Query\Builder;
use DateTime;

final class BelongsToMany extends BaseRelation
{
    protected readonly string $pivotTable;

    protected readonly string $foreignPivotKey;

    protected readonly string $relatedPivotKey;

    public readonly string $parentKey;

    public readonly string $relatedKey;

    protected bool $withTimestamps = true;

    protected string $createdAt = 'created_at';

    protected string $updatedAt = 'updated_at';

    protected array $pivotColumns = [];

    public array $eagerSelectColumns = ['*'];

    public function __construct(BaseModel $parent, string $relatedModel, string $pivotTable, string $foreignPivotKey, string $relatedPivotKey, string $parentKey, string $relatedKey)
    {
        parent::__construct($parent, $relatedModel);

        $this->pivotTable = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;

        $relatedQualifiedKey = $this->getRelatedTable() . '.' . $this->relatedKey;
        $pivotQualifiedKey = $this->getQualifiedRelatedPivotKeyName();

        $this->query->join($this->pivotTable, $pivotQualifiedKey, '=', $relatedQualifiedKey, 'INNER');

        $this->applyPivotSelect();
        $this->applyConstraints();
    }

    public function getRelatedTable(): string
    {
        return $this->getRelated()->getTable();
    }

    protected function applyPivotSelect(): void
    {
        $relatedTableName = $this->getRelatedTable();
        $relatedKey = $this->relatedKey;
        $baseColumns = $this->eagerSelectColumns;
        $requiredKey = $relatedTableName . '.' . $relatedKey;

        if ($baseColumns === ['*']) {
            $selects = [Builder::raw("{$relatedTableName}.*")];
        } else {
            $selects = [];
            $keyIsPresent = false;

            foreach ($baseColumns as $column) {
                $qualifiedColumn = $relatedTableName . '.' . $column;
                $selects[] = $qualifiedColumn;

                if ($column === $relatedKey) {
                    $keyIsPresent = true;
                }
            }

            if (! $keyIsPresent) {
                $selects[] = $requiredKey;
            }
        }

        $allSelects = $selects;

        $pivotColumns = array_unique(array_merge(
            [$this->foreignPivotKey, $this->relatedPivotKey],
            $this->withTimestamps ? [$this->createdAt, $this->updatedAt] : [],
            $this->pivotColumns
        ));

        foreach ($pivotColumns as $column) {
            $allSelects[] = Builder::raw("{$this->pivotTable}.{$column} AS pivot_{$column}");
        }

        $this->query->select($allSelects);
    }

    public function withPivot(string ...$columns): self
    {
        $this->pivotColumns = array_unique(array_merge($this->pivotColumns, $columns));
        $this->applyPivotSelect();

        return $this;
    }

    public function withTimestamps(string $createdAt = 'created_at', string $updatedAt = 'updated_at'): self
    {
        $this->withTimestamps = true;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->applyPivotSelect();

        return $this;
    }

    public function withoutTimestamps(): self
    {
        $this->withTimestamps = false;
        $this->applyPivotSelect();

        return $this;
    }

    protected function applyConstraints(): void
    {
        $value = $this->parent->{$this->parentKey};

        if (is_null($value)) {
            $this->query->whereRaw('0 = 1');

            return;
        }

        $this->query->where($this->getQualifiedForeignPivotKeyName(), '=', $value);
    }

    public function getResults(): ModelCollection
    {
        return $this->query->get();
    }

    public function addEagerConstraints(array $models): void
    {
        $this->query->removeWhere($this->getQualifiedForeignPivotKeyName());

        $keys = $this->getKeys($models, $this->parentKey);

        if (empty($keys)) {
            $this->query->whereRaw('0 = 1');

            return;
        }

        $this->query->whereIn($this->getQualifiedForeignPivotKeyName(), $keys);
    }

    public function match(array $models, array $results, string $relation): array
    {
        $results = $results instanceof ModelCollection ? $results->all() : $results;

        $dictionary = [];

        foreach ($results as $result) {
            $pivotAttributes = [];
            $resultAsArray = ($result instanceof BaseModel) ? $result->attributes : (array) $result;

            foreach ($resultAsArray as $key => $value) {
                $cleanKey = ltrim($key, "\x00*");

                if (str_starts_with($cleanKey, 'pivot_')) {
                    $pivotKey = substr($cleanKey, 6);
                    $pivotAttributes[$pivotKey] = $value;
                }
            }

            $result->pivot = $pivotAttributes;

            $parentKey = (string) ($pivotAttributes[$this->foreignPivotKey] ?? null);
            if ($parentKey !== null) {
                $dictionary[$parentKey][] = $result;
            }
        }

        foreach ($models as $model) {
            $key = (string) $model->{$this->parentKey};
            $model->setRelation($relation, new ModelCollection($dictionary[$key] ?? []));
        }

        return $models;
    }

    public function attach(int|array $id, array $pivotAttributes = []): int
    {
        $parentValue = $this->parent->{$this->parentKey};
        if (is_null($parentValue)) {
            return 0;
        }

        $insertData = [];
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $idsToAttach = [];

        if (is_array($id)) {
            foreach ($id as $relatedId => $attributes) {
                if (is_numeric($relatedId)) {
                    $idsToAttach[(int) $relatedId] = is_array($attributes) ? $attributes : $pivotAttributes;
                } else {
                    $idsToAttach[(int) $attributes] = $pivotAttributes;
                }
            }
        } else {
            $idsToAttach[(int) $id] = $pivotAttributes;
        }

        $currentIds = $this->getAttachedIds();

        foreach ($idsToAttach as $relatedId => $attributes) {
            if (! in_array($relatedId, $currentIds)) {
                $record = array_merge($attributes, [
                    $this->foreignPivotKey => $parentValue,
                    $this->relatedPivotKey => $relatedId,
                ]);

                if ($this->withTimestamps) {
                    $record[$this->createdAt] = $now;
                    $record[$this->updatedAt] = $now;
                }

                $insertData[] = $record;
            }
        }

        if (! empty($insertData)) {
            $builder = $this->parent::query()->from($this->pivotTable);
            $result = $builder->insert($insertData);

            return (int) (is_bool($result) ? count($insertData) : $result);
        }

        return 0;
    }

    protected function updateExistingPivot(int $id, array $attributes): int
    {
        if ($this->withTimestamps) {
            $attributes[$this->updatedAt] = (new DateTime())->format('Y-m-d H:i:s');
        }

        $query = $this->parent::query()->from($this->pivotTable)
            ->where($this->foreignPivotKey, '=', $this->parent->{$this->parentKey})
            ->where($this->relatedPivotKey, '=', $id);

        return $query->update($attributes);
    }

    protected function getAttachedIds(): array
    {
        $parentValue = $this->parent->{$this->parentKey};
        if (is_null($parentValue)) {
            return [];
        }

        return $this->parent::query()
            ->from($this->pivotTable)
            ->where($this->foreignPivotKey, '=', $parentValue)
            ->pluck($this->relatedPivotKey);
    }

    public function detach(int|array|null $ids = null): int
    {
        $parentValue = $this->parent->{$this->parentKey};
        if (is_null($parentValue)) {
            return 0;
        }

        $query = $this->parent::query()
            ->from($this->pivotTable)
            ->where($this->foreignPivotKey, '=', $parentValue);

        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    public function sync(array $ids): array
    {
        $idsToSync = [];
        foreach ($ids as $key => $attributes) {
            if (is_numeric($key)) {
                $idsToSync[(int) $attributes] = [];
            } else {
                $idsToSync[(int) $key] = $attributes;
            }
        }

        $currentIds = $this->getAttachedIds();
        $idKeys = array_keys($idsToSync);

        $detachIds = array_diff($currentIds, $idKeys);
        $attachIds = array_diff($idKeys, $currentIds);
        $updateIds = array_intersect($currentIds, $idKeys);

        $detached = $this->detach($detachIds);

        $attachData = array_intersect_key($idsToSync, array_flip($attachIds));
        $attached = $this->attach($attachData);

        $updatedCount = 0;
        foreach ($updateIds as $id) {
            $attributes = $idsToSync[$id];

            if (! empty($attributes) || $this->withTimestamps) {
                $updatedCount += $this->updateExistingPivot($id, $attributes);
            }
        }

        return [
            'attached' => $attached,
            'detached' => $detached,
            'updated' => $updatedCount,
        ];
    }

    public function syncWithoutDetaching(array $ids): array
    {
        $idsToSync = [];
        foreach ($ids as $key => $attributes) {
            if (is_numeric($key)) {
                $idsToSync[(int) $attributes] = [];
            } else {
                $idsToSync[(int) $key] = $attributes;
            }
        }

        $currentIds = $this->getAttachedIds();
        $idKeys = array_keys($idsToSync);

        $attachIds = array_diff($idKeys, $currentIds);
        $updateIds = array_intersect($currentIds, $idKeys);

        $attachData = array_intersect_key($idsToSync, array_flip($attachIds));
        $attached = $this->attach($attachData);

        $updatedCount = 0;
        foreach ($updateIds as $id) {
            $attributes = $idsToSync[$id];

            if (! empty($attributes) || $this->withTimestamps) {
                $updatedCount += $this->updateExistingPivot($id, $attributes);
            }
        }

        return [
            'attached' => $attached,
            'detached' => 0,
            'updated' => $updatedCount,
        ];
    }

    public function getForeignKey(): string
    {
        return $this->parentKey;
    }

    public function getQualifiedForeignKeyName(): string
    {
        return $this->parent->getTable() . '.' . $this->parentKey;
    }

    public function getQualifiedForeignPivotKeyName(): string
    {
        return "{$this->pivotTable}.{$this->foreignPivotKey}";
    }

    public function getQualifiedRelatedPivotKeyName(): string
    {
        return "{$this->pivotTable}.{$this->relatedPivotKey}";
    }

    public function getLocalKey(): string
    {
        return $this->relatedKey;
    }

    public function getMatchKey(): string
    {
        return $this->relatedKey;
    }
}
