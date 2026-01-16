<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * BaseModel is the core ORM layer of the framework.
 * It provides methods for database interaction, relationship management,
 * value casting, and event handling, serving as the parent for all application models.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database;

use Closure;
use Database\Collections\LazyCollection;
use Database\Collections\ModelCollection;
use Database\Exceptions\ValidationException;
use Database\Query\Builder;
use Database\Relations\BaseRelation;
use Database\Relations\BelongsTo;
use Database\Relations\HasOne;
use Database\Relations\MorphTo;
use Database\Traits\HasFactory;
use Database\Traits\HasRelations;
use Database\Traits\SoftDeletes;
use Debugger\Debugger;
use Helpers\DateTimeHelper;
use Helpers\File\Cache;
use JsonSerializable;
use RuntimeException;
use UnitEnum;

/**
 * @mixin \Database\Query\Builder
 */
class BaseModel implements JsonSerializable
{
    use HasRelations;
    use HasFactory;
    use SoftDeletes {
        delete as traitDelete;
        forceDelete as traitForceDelete;
        restore as traitRestore;
    }

    protected string $table;

    protected string $primaryKey = 'id';

    protected string $connection = 'default';

    public array $attributes = [];

    protected array $original = [];

    public array $pivot = [];

    protected array $relations = [];

    public bool $exists = false;

    protected static bool $autoEagerLoadEnabled = false;

    protected ?object $parentCollection = null;

    protected array $fillable = [];

    protected array $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected array $hidden = [];

    protected array $casts = [];

    protected array $rules = [];

    protected bool $timestamps = true;

    protected bool $softDeletes = false;

    protected ?string $createdAtColumn = 'created_at';

    protected ?string $updatedAtColumn = 'updated_at';

    protected static array $globalScopes = [];

    protected static array $events = [];

    protected static array $sortableColumns = ['id'];

    protected static array $booted = [];

    protected static ?ModelValidator $validator = null;

    public function __construct(array $attributes = [], bool $exists = false)
    {
        $this->bootIfNotBooted();

        if (is_null(static::$validator)) {
            static::$validator = new ModelValidator();
        }

        if (! isset($this->table)) {
            $parts = explode('\\', static::class);
            $className = end($parts);
            $this->table = strtolower($className);
        }

        $this->attributes = $attributes;
        $this->exists = $exists;

        if (! $exists) {
            $this->fill($attributes);
        }

        $this->original = $this->attributes;

        if (class_exists(Debugger::class)) {
            try {
                $debugger = Debugger::getInstance();
                if ($debugger->getDebugBar()->hasCollector('models') && method_exists($debugger->getDebugBar()['models'], 'addModel')) {
                    $debugger->getDebugBar()['models']->addModel(static::class, $this->attributes);
                }
            } catch (RuntimeException $e) {
                // Debugger not initialized, ignore
            }
        }
    }

    public function save(): bool
    {
        $isInsert = ! isset($this->attributes[$this->primaryKey]);
        if ($this->fireEvent('saving') === false) {
            return false;
        }

        $this->validate();

        if ($isInsert) {
            return $this->performInsert();
        }

        return $this->performUpdate();
    }

    public function update(array $attributes = []): bool
    {
        $this->fill($attributes);

        return $this->save();
    }

    public function refresh(): bool
    {
        $id = $this->getPrimaryKeyValue();

        if (is_null($id)) {
            return false;
        }

        $fresh = static::find($id);

        if (is_null($fresh)) {
            return false;
        }

        $this->attributes = $fresh->attributes;
        $this->original = $fresh->attributes;
        $this->relations = [];

        if (! is_null($this->parentCollection)) {
            $fresh->setParentCollection($this->parentCollection);
        }

        $this->pivot = $fresh->pivot;

        return true;
    }

    public function fresh(array|string $with = []): ?static
    {
        if (! $this->exists) {
            return null;
        }

        return static::with(is_string($with) ? func_get_args() : $with)
            ->where($this->getPrimaryKey(), $this->getPrimaryKeyValue())
            ->first();
    }

    public static function create(array $attributes): static
    {
        $model = new static();
        $model->fill($attributes);

        if (! $model->save()) {
            throw new RuntimeException('Failed to create model: ' . static::class);
        }

        return $model;
    }

    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $model = static::query()->where($attributes)->first();

        if (is_null($model)) {
            return static::create(array_merge($attributes, $values));
        }

        return $model;
    }

    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $model = static::query()->where($attributes)->first();

        if (is_null($model)) {
            return static::create(array_merge($attributes, $values));
        }

        $model->update($values);

        return $model;
    }

    protected function performInsert(): bool
    {
        if ($this->fireEvent('creating') === false) {
            return false;
        }

        if ($this->timestamps) {
            $now = DateTimeHelper::now()->format('Y-m-d H:i:s');

            if ($this->createdAtColumn && ! isset($this->attributes[$this->createdAtColumn])) {
                $this->attributes[$this->createdAtColumn] = $this->castAttributeOnSet($this->createdAtColumn, $now);
            }

            if ($this->updatedAtColumn && ! isset($this->attributes[$this->updatedAtColumn])) {
                $this->attributes[$this->updatedAtColumn] = $this->castAttributeOnSet($this->updatedAtColumn, $now);
            }
        }

        $data = $this->getAttributesForBinding($this->attributes);

        $id = static::query()->insertGetId($data);
        $this->attributes[$this->primaryKey] = $id;
        $this->exists = true;

        $this->fireEvent('created');
        $this->fireEvent('saved');
        $this->original = $this->attributes;

        static::flushQueryCache();

        return true;
    }

    protected function performUpdate(): bool
    {
        if ($this->fireEvent('updating') === false) {
            return false;
        }

        $changes = array_diff_assoc($this->attributes, $this->original);
        unset($changes[$this->primaryKey]);

        if (empty($changes)) {
            return true;
        }

        if ($this->timestamps && $this->updatedAtColumn) {
            $now = DateTimeHelper::now()->format('Y-m-d H:i:s');
            $timestampValue = $this->castAttributeOnSet($this->updatedAtColumn, $now);

            $changes[$this->updatedAtColumn] = $timestampValue;
            $this->attributes[$this->updatedAtColumn] = $timestampValue;
        }

        $data = $this->getAttributesForBinding($changes);

        $result = static::query()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->update($data);

        $this->fireEvent('updated');
        $this->fireEvent('saved');
        $this->original = $this->attributes;

        static::flushQueryCache();

        return (bool) $result;
    }

    public function increment(string $column, float|int $amount = 1, array $extra = []): int
    {
        return static::query()->where($this->getPrimaryKey(), $this->getPrimaryKeyValue())->increment($column, $amount, $extra);
    }

    public function decrement(string $column, float|int $amount = 1, array $extra = []): int
    {
        return static::query()->where($this->getPrimaryKey(), $this->getPrimaryKeyValue())->decrement($column, $amount, $extra);
    }

    public function delete(): bool
    {
        if ($this->traitDelete()) {
            static::flushQueryCache();

            return true;
        }

        return false;
    }

    public function forceDelete(): bool
    {
        if ($this->traitForceDelete()) {
            static::flushQueryCache();

            return true;
        }

        return false;
    }

    public function restore(): bool
    {
        if ($this->traitRestore()) {
            static::flushQueryCache();

            return true;
        }

        return false;
    }

    public function usesSoftDeletes(): bool
    {
        return $this->softDeletes === true;
    }

    protected function validate(): void
    {
        if (empty($this->rules)) {
            return;
        }

        $errors = static::$validator->validate($this->attributes, $this->rules, static::class, $this->primaryKey, $this->attributes[$this->primaryKey] ?? null);

        if (! empty($errors)) {
            throw new ValidationException('Validation failed for ' . static::class, $errors);
        }
    }

    public static function whereHas(string $relationName, ?callable $callback = null, string $boolean = 'AND'): Builder
    {
        $instance = new static();
        $parentBuilder = $instance->query();
        $parentTable = $instance->getTable();
        $parentKey = $instance->getPrimaryKey();

        $relation = $instance->getRelation($relationName);
        $relatedModel = $relation->getRelated();
        $relatedTable = $relatedModel->getTable();
        $foreignKey = $relation->getForeignKey();
        $existenceQuery = $relation->getExistenceQuery();

        $existenceQuery->whereRaw("{$relatedTable}.{$foreignKey} = {$parentTable}.{$parentKey}");

        if ($callback) {
            $callback($existenceQuery);
        }

        if (empty($existenceQuery->getSelects())) {
            $existenceQuery->select('1');
        }

        $parentBuilder->whereExists($existenceQuery, $boolean);

        return $parentBuilder;
    }

    public static function lazy(): LazyCollection
    {
        return new LazyCollection(static::query());
    }

    private function getAttributesForBinding(array $attributes): array
    {
        $data = [];
        foreach ($attributes as $key => $value) {
            $data[$key] = $this->castAttributeOnSet($key, $value);
        }

        return $data;
    }

    public function castAttributeOnSet(string $key, mixed $value): mixed
    {
        if (! isset($this->casts[$key]) || is_null($value)) {
            return $value;
        }

        $castType = $this->casts[$key];

        if (class_exists($castType) && is_a($value, $castType)) {
            if (enum_exists($castType) && $value instanceof UnitEnum) {
                return $value->value;
            }
        }

        switch ($castType) {
            case 'datetime':
                return ($value instanceof DateTimeHelper) ? $value->format('Y-m-d H:i:s') : $value;
            case 'date':
                return ($value instanceof DateTimeHelper) ? $value->format('Y-m-d') : $value;
            case 'json':
            case 'array':
                return is_array($value) || is_object($value) ? json_encode($value) : $value;
            case 'int':
            case 'integer':
                return (int) $value;
            case 'bool':
            case 'boolean':
                // Convert to integer (0 or 1) for database storage
                if (is_bool($value)) {
                    return $value ? 1 : 0;
                }
                $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                return $filtered !== null ? ($filtered ? 1 : 0) : (int) (bool) $value;
            case 'float':
            case 'double':
            case 'real':
                return (float) $value;
            case 'string':
            case 'enum':
                return (string) $value;
        }

        return $value;
    }

    protected function castAttributeOnGet(string $key, mixed $value): mixed
    {
        if (! isset($this->casts[$key]) || is_null($value)) {
            return $value;
        }

        $castType = $this->casts[$key];

        if (class_exists($castType) && enum_exists($castType) && method_exists($castType, 'tryFrom')) {
            return $castType::tryFrom($value);
        }

        switch ($castType) {
            case 'datetime':
            case 'date':
                return DateTimeHelper::parse($value);
            case 'int':
            case 'integer':
                return (int) $value;
            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
            case 'float':
            case 'double':
            case 'real':
                return (float) $value;
            case 'string':
            case 'enum':
                return (string) $value;
            case 'json':
            case 'array':
                $decoded = json_decode($value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        return $value;
    }

    public function getConnectionName(): string
    {
        return $this->connection;
    }

    public static function setConnection(ConnectionInterface $connection, string $name = 'default'): void
    {
        DB::addConnection($name, $connection);
    }

    protected static function getModelConnection(): ConnectionInterface
    {
        $instance = new static();

        return DB::connection($instance->getConnectionName());
    }

    public static function getSortableColumns(): array
    {
        return static::$sortableColumns;
    }

    public static function isAutoEagerLoadEnabled(): bool
    {
        return static::$autoEagerLoadEnabled;
    }

    public static function query(): Builder
    {
        $instance = new static();

        $builder = static::getModelConnection()
            ->table($instance->getTable())
            ->setModelClass(static::class);

        $instance->applyGlobalScopes($builder);

        return $builder;
    }

    public static function find(int|string $id): ?static
    {
        return static::query()->where((new static())->getPrimaryKey(), '=', $id)->first();
    }

    public static function flushQueryCache(): bool
    {
        return Cache::create('query')->withPath((new static())->getTable())->clear();
    }

    public static function findMany(array $ids, array $columns = ['*']): ModelCollection
    {
        $instance = new static();

        if (empty($ids)) {
            return new ModelCollection([]);
        }

        $query = static::query()->whereIn($instance->getPrimaryKey(), $ids);

        if ($columns !== ['*']) {
            $query->select($columns);
        }

        return $query->get();
    }

    public static function all(array $columns = ['*']): ModelCollection
    {
        $query = static::query();

        if ($columns !== ['*']) {
            $query->select($columns);
        }

        return $query->get();
    }

    public static function with(string|array $relations): Builder
    {
        $builder = static::query();
        $relations = is_array($relations) ? $relations : func_get_args();

        return $builder->setEagerLoads($relations);
    }

    public function load(string|array $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();
        $loadedModels = static::eagerLoadRelations([$this], $relations, []);

        $loadedModel = array_shift($loadedModels);

        foreach ($loadedModel->relations as $key => $value) {
            $this->setRelation($key, $value);
        }

        return $this;
    }

    public function loadMissing(string|array $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();
        $missingRelations = [];

        foreach ($relations as $key => $value) {
            $relationName = is_int($key) ? $value : $key;
            $rootRelation = explode('.', $relationName, 2)[0];

            if (! array_key_exists($rootRelation, $this->relations) || is_null($this->relations[$rootRelation])) {
                $missingRelations[$key] = $value;
            }
        }

        if (! empty($missingRelations)) {
            $this->load($missingRelations);
        }

        return $this;
    }

    public function loadCount(string|array $aggregates): self
    {
        $aggregates = is_array($aggregates) ? $aggregates : func_get_args();

        $loadedModels = static::eagerLoadAggregates([$this], $aggregates, 'count', null);
        $loadedModel = array_shift($loadedModels);

        foreach ($loadedModel->attributes as $key => $value) {
            if ($this->isAggregateAttribute($key, $aggregates, 'count')) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    public function loadCountMissing(string|array $aggregates): self
    {
        $aggregates = is_array($aggregates) ? $aggregates : func_get_args();
        $missingAggregates = [];

        foreach ($aggregates as $key => $value) {
            $alias = is_string($key) ? $key : (is_string($value) ? $value . '_count' : null);

            if ($alias && ! array_key_exists($alias, $this->attributes)) {
                $missingAggregates[$key] = $value;
            }
        }

        if (! empty($missingAggregates)) {
            $this->loadCount($missingAggregates);
        }

        return $this;
    }

    protected function isAggregateAttribute(string $key, array $aggregates, string $function): bool
    {
        foreach ($aggregates as $aggregateKey => $value) {
            if (is_int($aggregateKey) && $key === $value . '_' . $function) {
                return true;
            }

            if (is_string($aggregateKey) && $key === $aggregateKey) {
                return true;
            }
        }

        return false;
    }

    public static function eagerLoadAggregates(array $models, array $aggregates, ?string $function = null, ?string $column = null): array
    {
        if (empty($models) || empty($aggregates)) {
            return $models;
        }

        $instance = $models[0];
        $parentKey = $instance->getPrimaryKey();
        $modelIds = [];

        foreach ($models as $model) {
            $id = $model->getPrimaryKeyValue();
            if ($id !== null) {
                $modelIds[] = $id;
            }
        }

        if (empty($modelIds)) {
            return $models;
        }

        foreach ($aggregates as $key => $value) {
            $constraint = null;
            if (is_array($value) && isset($value['relation'], $value['function'], $value['column'], $value['alias'])) {
                $relationName = $value['relation'];
                $function = $value['function'];
                $column = $value['column'];
                $alias = $value['alias'];
            } else {
                $relationName = is_int($key) ? $value : $key;
                $constraint = is_int($key) ? null : $value;

                $parts = explode(' as ', $relationName);
                $alias = $parts[1] ?? ($parts[0] . '_' . ($function ?? 'count') . ($column ? ('_' . $column) : ''));
                $relationName = $parts[0];
            }

            $relation = $instance->getRelation($relationName);

            if (! $relation instanceof BaseRelation) {
                continue;
            }

            $query = $relation->getExistenceQuery();
            $relatedModel = $relation->getRelated();
            $foreignKey = $relation->getForeignKey();

            if ($constraint instanceof Closure) {
                $constraint($query);
            }

            $selectRaw = "{$relation->getForeignKey()} as aggregate_key, {$function}(" . ($column ?? '*') . ") as {$alias}";

            $results = $query
                ->selectRaw($selectRaw)
                ->whereIn("{$relatedModel->getTable()}.{$foreignKey}", $modelIds)
                ->groupBy("{$relatedModel->getTable()}.{$foreignKey}")
                ->get();

            $resultsMap = [];
            foreach ($results as $result) {
                $key = $result->aggregate_key ?? $result->attributes['aggregate_key'];
                $resultsMap[$key] = $result;
            }

            foreach ($models as $model) {
                $aggregateValue = 0;
                $currentId = $model->getPrimaryKeyValue();

                if (isset($resultsMap[$currentId])) {
                    $result = $resultsMap[$currentId];
                    $aggregateValue = (int) ($result->{$alias} ?? $result->attributes[$alias]);
                }

                $model->attributes[$alias] = $aggregateValue;
            }
        }

        return $models;
    }

    public static function eagerLoadRelations(array $models, array $eagerLoads, array $aggregates = []): array
    {
        if (empty($models) || empty($eagerLoads)) {
            return $models;
        }

        $instance = $models[0];
        $rootRelations = [];

        foreach ($eagerLoads as $key => $value) {
            $relationStr = is_int($key) ? $value : $key;
            $constraint = is_int($key) ? null : $value;

            $columns = null;
            if (is_string($relationStr) && str_contains($relationStr, ':')) {
                [$relationStr, $columnString] = explode(':', $relationStr, 2);
                $columns = array_map('trim', explode(',', $columnString));
            }

            $parts = explode('.', $relationStr, 2);
            $root = $parts[0];
            $nestedPart = $parts[1] ?? null;

            if (! isset($rootRelations[$root])) {
                $rootRelations[$root] = ['constraint' => null, 'nested' => [], 'columns' => null];
            }

            if ($constraint instanceof Closure) {
                $rootRelations[$root]['constraint'] = $constraint;
            }

            if ($columns) {
                $rootRelations[$root]['columns'] = $columns;
            }

            if ($nestedPart) {
                $rootRelations[$root]['nested'][] = $nestedPart;
            }
        }

        foreach ($rootRelations as $rootRelationName => $data) {
            $relation = $instance->getRelation($rootRelationName);

            if (! $relation instanceof BaseRelation) {
                continue;
            }

            $query = $relation->query;
            $relatedModel = $relation->getRelated();
            $relatedTable = $relatedModel->getTable();

            $columns = $data['columns'];

            if ($columns) {
                if ($relation instanceof BelongsTo) {
                    $requiredKey = $relatedTable . '.' . $relatedModel->getPrimaryKey();
                } else {
                    $requiredKey = $relatedTable . '.' . $relation->getForeignKey();
                }

                $selects = [];
                $keyIsPresent = false;

                foreach ($columns as $column) {
                    $qualifiedColumn = $relatedTable . '.' . $column;
                    $selects[] = $qualifiedColumn;

                    if ($qualifiedColumn === $requiredKey || $column === $relatedModel->getPrimaryKey()) {
                        $keyIsPresent = true;
                    }
                }

                if (! $keyIsPresent) {
                    $selects[] = $requiredKey;
                }

                $query->select($selects);
            }

            $constraint = $data['constraint'] ?? null;
            if (is_callable($constraint)) {
                $constraint($query);
            }

            if ($relation instanceof MorphTo) {
                $types = [];
                foreach ($models as $model) {
                    $type = $model->{$relation->typeColumn};
                    $id = $model->{$relation->idColumn};
                    if ($type && $id) {
                        $types[$type][] = $id;
                    }
                }

                $relatedResults = [];
                foreach ($types as $type => $ids) {
                    if (!class_exists($type)) {
                        continue;
                    }
                    $results = $type::query()->whereIn((new $type())->getPrimaryKey(), array_unique($ids))->get();
                    $relatedResults = array_merge($relatedResults, $results instanceof ModelCollection ? $results->all() : $results);
                }
            } else {
                if ($relation instanceof HasOne && property_exists($relation, 'isOfMany') && $relation->isOfMany()) {
                    $relation->applyOfManyEagerConstraint($query, $models);
                } else {
                    $relation->addEagerConstraints($models);
                }

                $relatedResults = $query->get()->all();
            }

            $models = $relation->match($models, $relatedResults, $rootRelationName);

            if (! empty($data['nested'])) {
                $resultsByModel = [];
                foreach ($models as $model) {
                    $related = $model->{$rootRelationName} ?? null;

                    if ($related instanceof ModelCollection) {
                        $resultsByModel = array_merge($resultsByModel, $related->all());
                    } elseif ($related instanceof self) {
                        $resultsByModel[] = $related;
                    }
                }

                if (! empty($resultsByModel)) {
                    static::eagerLoadRelations($resultsByModel, array_unique($data['nested']), []);
                }
            }
        }

        return $models;
    }

    public static function addGlobalScope(string $identifier, callable $callback): void
    {
        static::$globalScopes[static::class][$identifier] = $callback;
    }

    protected function applyGlobalScopes(Builder $builder): void
    {
        $scopes = static::$globalScopes[static::class] ?? [];

        foreach ($scopes as $identifier => $scope) {
            $builder->addGlobalScope($identifier, $scope);
        }
    }

    public static function withoutGlobalScope(string $scope): Builder
    {
        $builder = static::query();

        return $builder->withoutGlobalScope($scope);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getPrimaryKeyValue(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getUpdatedAtColumn(): ?string
    {
        return $this->updatedAtColumn;
    }

    public function getUpdateTimestamps(): array
    {
        if (! $this->timestamps || ! $this->updatedAtColumn) {
            return [];
        }

        $now = DateTimeHelper::now()->format('Y-m-d H:i:s');

        return [
            $this->updatedAtColumn => $now,
        ];
    }

    public static function automaticallyEagerLoadRelationships(): void
    {
        static::$autoEagerLoadEnabled = true;
    }

    public function setParentCollection(object $collection): self
    {
        if ($collection instanceof ModelCollection) {
            $this->parentCollection = $collection;
        }

        return $this;
    }

    public function getOriginal(): array
    {
        return $this->original;
    }

    public function isDirty(?string $attribute = null): bool
    {
        $dirty = array_diff_assoc($this->attributes, $this->original);

        if (is_null($attribute)) {
            return ! empty($dirty);
        }

        return array_key_exists($attribute, $dirty);
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function setRelation(string $relationName, mixed $value): void
    {
        $this->relations[$relationName] = $value;
    }

    public function only(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $value = $this->{$key} ?? null;
            if (array_key_exists($key, $this->attributes) || array_key_exists($key, $this->relations) || method_exists($this, 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute')) {

                if (is_object($value) && method_exists($value, 'toArray')) {
                    $result[$key] = $value->toArray();
                } elseif (is_array($value)) {
                    $result[$key] = array_map(function ($item) {
                        return method_exists($item, 'toArray') ? $item->toArray() : $item;
                    }, $value);
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    public function toArray(): array
    {
        $attributes = $this->attributes;

        foreach ($this->hidden as $key) {
            unset($attributes[$key]);
        }

        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->castAttributeOnGet($key, $value);
        }

        foreach ($this->relations as $key => $relation) {
            if (in_array($key, $this->hidden)) {
                continue;
            }

            if (is_object($relation) && method_exists($relation, 'toArray')) {
                $attributes[$key] = $relation->toArray();
            } elseif (is_array($relation)) {
                $attributes[$key] = array_map(function ($item) {
                    return method_exists($item, 'toArray') ? $item->toArray() : $item;
                }, $relation);
            } else {
                $attributes[$key] = $relation;
            }
        }

        return $attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $isFillable = in_array($key, $this->fillable) || (empty($this->fillable) && ! in_array($key, $this->guarded));

            if ($isFillable) {
                $this->{$key} = $value;
            }
        }

        return $this;
    }

    public function __set(string $key, $value): void
    {
        $mutatorMethod = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';

        if (method_exists($this, $mutatorMethod)) {
            $this->{$mutatorMethod}($value);

            return;
        }

        $this->attributes[$key] = $this->castAttributeOnSet($key, $value);
    }

    public function __get(string $key): mixed
    {
        $accessorMethod = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';

        if (method_exists($this, $accessorMethod)) {
            return $this->{$accessorMethod}();
        }

        if (array_key_exists($key, $this->attributes)) {
            return $this->castAttributeOnGet($key, $this->attributes[$key]);
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            if (static::$autoEagerLoadEnabled && $this->parentCollection instanceof ModelCollection) {
                $this->parentCollection->loadMissing($key);

                return $this->relations[$key] ?? null;
            }

            return $this->getRelationResults($key);
        }

        return null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    public function getDirty(): array
    {
        return array_diff_assoc($this->attributes, $this->original);
    }

    public function isNew(): bool
    {
        return ! isset($this->attributes[$this->primaryKey]);
    }

    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function __call(string $method, array $parameters): mixed
    {
        $scopeMethod = 'scope' . ucfirst($method);

        if (method_exists($this, $scopeMethod)) {
            return $this->{$scopeMethod}(static::query(), ...$parameters);
        }

        if (method_exists($this, $method)) {
            $relation = $this->{$method}();
            if ($relation instanceof BaseRelation) {
                return $relation->getQuery();
            }
        }

        throw new RuntimeException("Method {$method} not found on " . static::class);
    }

    protected function getRelationQueryData(string $name): array
    {
        throw new RuntimeException('getRelationQueryData is deprecated and should not be called.');
    }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        $instance = new static();
        $builder = static::query();

        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists(static::class, $scopeMethod)) {
            $parametersWithBuilder = array_merge([$builder], $parameters);

            return $instance->{$scopeMethod}(...$parametersWithBuilder);
        }

        if (method_exists($instance, $method)) {
            $relation = $instance->{$method}();
            if ($relation instanceof BaseRelation) {
                return $relation->getQuery();
            }
        }

        if (method_exists($builder, $method)) {
            return $builder->{$method}(...$parameters);
        }

        throw new RuntimeException("Static method {$method} not found on " . static::class);
    }

    public function is(BaseModel $other): bool
    {
        $primaryKey = $this->getPrimaryKey();

        if (static::class !== get_class($other)) {
            return false;
        }

        if (! isset($this->attributes[$primaryKey]) || ! isset($other->attributes[$primaryKey])) {
            return false;
        }

        return $this->attributes[$primaryKey] === $other->attributes[$primaryKey];
    }

    protected function fireEvent(string $event): bool
    {
        $listeners = static::$events[static::class][$event] ?? [];
        foreach ($listeners as $callback) {
            if ($callback($this) === false) {
                return false;
            }
        }

        return true;
    }

    public static function creating(callable $callback): void
    {
        static::registerEvent('creating', $callback);
    }

    public static function created(callable $callback): void
    {
        static::registerEvent('created', $callback);
    }

    public static function updating(callable $callback): void
    {
        static::registerEvent('updating', $callback);
    }

    public static function updated(callable $callback): void
    {
        static::registerEvent('updated', $callback);
    }

    public static function saving(callable $callback): void
    {
        static::registerEvent('saving', $callback);
    }

    public static function saved(callable $callback): void
    {
        static::registerEvent('saved', $callback);
    }

    public static function deleting(callable $callback): void
    {
        static::registerEvent('deleting', $callback);
    }

    public static function deleted(callable $callback): void
    {
        static::registerEvent('deleted', $callback);
    }

    public static function retrieved(callable $callback): void
    {
        static::registerEvent('retrieved', $callback);
    }

    public static function registerEvent(string $event, callable $callback): void
    {
        static::$events[static::class][$event][] = $callback;
    }

    protected function bootIfNotBooted(): void
    {
        if (! isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            static::boot();
        }
    }

    protected static function boot(): void
    {
        static::bootTraits();
    }

    protected static function bootTraits(): void
    {
        $class = static::class;
        $booted = [];

        $classes = array_merge([$class], class_parents($class));
        foreach ($classes as $c) {
            foreach (class_uses($c) as $trait) {
                if (isset($booted[$trait])) {
                    continue;
                }
                $booted[$trait] = true;

                $parts = explode('\\', $trait);
                $method = 'boot' . end($parts);
                if (method_exists($class, $method)) {
                    static::{$method}();
                }
            }
        }
    }

    public static function clearBootedState(): void
    {
        static::$booted = [];
        static::$events = [];
        static::$globalScopes = [];
    }
}
