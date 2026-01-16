<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Collection class for models.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Collections;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Database\BaseModel;
use IteratorAggregate;
use JsonSerializable;
use RuntimeException;
use Traversable;

class ModelCollection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected array $models = [];

    protected static bool $autoEagerLoadEnabled = false;

    public function __construct(array $models = [])
    {
        $this->models = $models;
        $this->linkModelsToCollection();
    }

    protected function linkModelsToCollection(): void
    {
        if (BaseModel::isAutoEagerLoadEnabled()) {
            foreach ($this->models as $model) {
                if ($model instanceof BaseModel) {
                    $model->setParentCollection($this);
                }
            }
        }
    }

    public function all(): array
    {
        return $this->models;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->models);
    }

    public function count(): int
    {
        return count($this->models);
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->models);
    }

    public function mapWithKeys(callable $callback): array
    {
        $result = [];

        foreach ($this->models as $key => $model) {
            foreach ($callback($model, $key) as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return $result;
    }

    public function load(string|array $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        if ($this->isEmpty()) {
            return $this;
        }

        $loadedModels = BaseModel::eagerLoadRelations($this->models, $relations, []);

        $keyedLoaded = [];
        foreach ($loadedModels as $model) {
            $keyedLoaded[$model->getPrimaryKeyValue()] = $model;
        }

        foreach ($this->models as $model) {
            $key = $model->getPrimaryKeyValue();
            if (isset($keyedLoaded[$key])) {
                foreach ($keyedLoaded[$key]->getRelations() as $relName => $relValue) {
                    $model->setRelation($relName, $relValue);
                }
            }
        }

        return $this;
    }

    public function loadMissing(string|array $relations): self
    {
        $relations = is_array($relations) ? $relations : [$relations];
        $relationsToLoad = [];

        if ($this->isEmpty()) {
            return $this;
        }

        foreach ($relations as $relationName) {
            $isMissing = false;
            foreach ($this->models as $model) {
                $modelRelations = $model->relations ?? [];

                if (! array_key_exists($relationName, $modelRelations)) {
                    $isMissing = true;
                    break;
                }
            }

            if ($isMissing) {
                $relationsToLoad[] = $relationName;
            }
        }

        if (! empty($relationsToLoad)) {
            $this->load($relationsToLoad);
        }

        return $this;
    }

    public function first(): ?BaseModel
    {
        if (empty($this->models)) {
            return null;
        }

        return reset($this->models);
    }

    public function isEmpty(): bool
    {
        return empty($this->models);
    }

    public function toArray(): array
    {
        return array_map(fn ($model) => $model->toArray(), $this->models);
    }

    public function filter(callable $callback): self
    {
        $filtered = array_filter($this->models, $callback, ARRAY_FILTER_USE_BOTH);

        return new static($filtered);
    }

    public function last(): ?BaseModel
    {
        if (empty($this->models)) {
            return null;
        }
        $values = array_values($this->models);

        return end($values) ?: null;
    }

    public function values(): self
    {
        return new static(array_values($this->models));
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function where(string $key, mixed $value): self
    {
        return $this->filter(function ($model) use ($key, $value) {
            return $model->{$key} == $value;
        });
    }

    public function reject(callable $callback): self
    {
        return $this->filter(function ($model, $key) use ($callback) {
            return ! $callback($model, $key);
        });
    }

    public function unique(string $key): self
    {
        $seen = [];
        $unique = [];

        foreach ($this->models as $model) {
            $value = $model->{$key};
            if (! in_array($value, $seen, true)) {
                $seen[] = $value;
                $unique[] = $model;
            }
        }

        return new static($unique);
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $result = [];

        foreach ($this->models as $model) {
            $value = $model->{$column};
            if ($key === null) {
                $result[] = $value;
            } else {
                $result[$model->{$key}] = $value;
            }
        }

        return $result;
    }

    public function transform(callable $callback): self
    {
        foreach ($this->models as $index => $model) {
            $this->models[$index] = $callback($model);
        }

        return $this;
    }

    public function sum(string $column): int|float
    {
        return array_sum($this->pluck($column));
    }

    public function avg(string $column): int|float
    {
        $count = $this->count();

        return $count > 0 ? $this->sum($column) / $count : 0;
    }

    public function min(string $column): mixed
    {
        $values = $this->pluck($column);

        return empty($values) ? null : min($values);
    }

    public function max(string $column): mixed
    {
        $values = $this->pluck($column);

        return empty($values) ? null : max($values);
    }

    public function sortBy(string $column, bool $descending = false): self
    {
        $models = $this->models;
        usort($models, function ($a, $b) use ($column, $descending) {
            $aVal = $a->{$column};
            $bVal = $b->{$column};

            if ($aVal == $bVal) {
                return 0;
            }

            $result = $aVal < $bVal ? -1 : 1;

            return $descending ? -$result : $result;
        });

        return new static($models);
    }

    public function sortByDesc(string $column): self
    {
        return $this->sortBy($column, true);
    }

    public function sort(callable $callback): self
    {
        $models = $this->models;
        usort($models, $callback);

        return new static($models);
    }

    public function reverse(): self
    {
        return new static(array_reverse($this->models));
    }

    public function chunk(int $size): array
    {
        $chunks = [];
        $models = array_values($this->models);

        for ($i = 0; $i < count($models); $i += $size) {
            $chunks[] = new static(array_slice($models, $i, $size));
        }

        return $chunks;
    }

    public function split(int $numberOfGroups): array
    {
        $count = $this->count();
        if ($count === 0) {
            return [];
        }

        $groupSize = (int) ceil($count / $numberOfGroups);

        return $this->chunk($groupSize);
    }

    public function take(int $limit): self
    {
        if ($limit < 0) {
            return new static(array_slice($this->models, $limit));
        }

        return new static(array_slice($this->models, 0, $limit));
    }

    public function skip(int $count): self
    {
        return new static(array_slice($this->models, $count));
    }

    public function groupBy(string|callable $groupBy): array
    {
        $groups = [];

        foreach ($this->models as $model) {
            $key = is_callable($groupBy) ? $groupBy($model) : $model->{$groupBy};

            if (! isset($groups[$key])) {
                $groups[$key] = [];
            }

            $groups[$key][] = $model;
        }

        return $groups;
    }

    public function each(callable $callback): self
    {
        foreach ($this->models as $key => $model) {
            if ($callback($model, $key) === false) {
                break;
            }
        }

        return $this;
    }

    public function find(int|string $id): ?BaseModel
    {
        foreach ($this->models as $model) {
            if ($model->getPrimaryKeyValue() == $id) {
                return $model;
            }
        }

        return null;
    }

    public function contains(mixed $value): bool
    {
        if ($value instanceof BaseModel) {
            foreach ($this->models as $item) {
                if ($item->getPrimaryKeyValue() == $value->getPrimaryKeyValue()) {
                    return true;
                }
            }

            return false;
        }

        if (is_callable($value)) {
            foreach ($this->models as $key => $item) {
                if ($value($item, $key)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($this->models as $item) {
            if ($item->getPrimaryKeyValue() == $value) {
                return true;
            }
        }

        return false;
    }

    public function merge(ModelCollection $collection): self
    {
        return new static(array_merge($this->models, $collection->all()));
    }

    public function diff(ModelCollection $collection): self
    {
        $diffModels = [];
        $collectionIds = [];

        foreach ($collection->all() as $collectionModel) {
            $collectionIds[] = $collectionModel->getPrimaryKeyValue();
        }

        foreach ($this->models as $model) {
            if (! in_array($model->getPrimaryKeyValue(), $collectionIds, true)) {
                $diffModels[] = $model;
            }
        }

        return new static($diffModels);
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->models[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->models[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (! $value instanceof BaseModel) {
            throw new RuntimeException('Only BaseModel instances can be added to a ModelCollection.');
        }

        if (is_null($offset)) {
            $this->models[] = $value;
        } else {
            $this->models[$offset] = $value;
        }

        $this->linkModelsToCollection();
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->models[$offset]);
        $this->models = array_values($this->models);
        $this->linkModelsToCollection();
    }
}
