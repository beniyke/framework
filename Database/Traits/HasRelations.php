<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for managing model relations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Traits;

use Closure;
use Database\BaseModel;
use Database\Query\Builder;
use Database\Relations\BaseRelation;
use Database\Relations\BelongsTo;
use Database\Relations\BelongsToMany;
use Database\Relations\HasMany;
use Database\Relations\HasManyThrough;
use Database\Relations\HasOne;
use Database\Relations\MorphMany;
use Database\Relations\MorphOne;
use Database\Relations\MorphTo;
use ReflectionClass;

trait HasRelations
{
    abstract public function getTable(): string;

    abstract public function getPrimaryKey(): string;

    abstract public function getPrimaryKeyValue(): mixed;

    abstract public function setRelation(string $key, mixed $value): void;

    abstract public static function query(): Builder;

    abstract public function __get(string $key): mixed;

    protected function getClassNameSnakeCase(string $modelClass): string
    {
        $className = (new ReflectionClass($modelClass))->getShortName();

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }

    protected function singularizeTableName(string $tableName): string
    {
        return (string) preg_replace('/s$/', '', $tableName);
    }

    public function belongsTo(string $relatedModel, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $relatedModel();

        if (is_null($foreignKey)) {
            $foreignKey = $this->getClassNameSnakeCase($relatedModel) . '_id';
        }

        $ownerKey = $ownerKey ?? $instance->getPrimaryKey();

        return new BelongsTo($this, $relatedModel, $foreignKey, $ownerKey);
    }

    public function hasMany(string $relatedModel, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        if (is_null($foreignKey)) {
            $foreignKey = $this->getClassNameSnakeCase(static::class) . '_id';
        }

        $localKey = $localKey ?? $this->getPrimaryKey();

        return new HasMany($this, $relatedModel, $foreignKey, $localKey);
    }

    public function hasOne(string $relatedModel, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        if (is_null($foreignKey)) {
            $foreignKey = $this->getClassNameSnakeCase(static::class) . '_id';
        }

        $localKey = $localKey ?? $this->getPrimaryKey();

        return new HasOne($this, $relatedModel, $foreignKey, $localKey);
    }

    public function belongsToMany(string $relatedModel, ?string $pivotTable = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null): BelongsToMany
    {
        $relatedInstance = new $relatedModel();
        $parentTable = $this->getTable();
        $relatedTable = $relatedInstance->getTable();

        $singularParent = $this->singularizeTableName($parentTable);
        $singularRelated = $this->singularizeTableName($relatedTable);

        if (is_null($pivotTable)) {
            $tables = [$singularParent, $singularRelated];
            sort($tables);
            $pivotTable = implode('_', $tables);
        }

        $foreignPivotKey = $foreignPivotKey ?? $singularParent . '_id';
        $relatedPivotKey = $relatedPivotKey ?? $singularRelated . '_id';

        $parentKey = $parentKey ?? $this->getPrimaryKey();
        $relatedKey = $relatedKey ?? $relatedInstance->getPrimaryKey();

        return new BelongsToMany($this, $relatedModel, $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey);
    }

    public function hasManyThrough(string $relatedModel, string $throughModel, ?string $firstKey = null, ?string $secondKey = null, ?string $throughLocalKey = null, ?string $relatedLocalKey = null): HasManyThrough
    {
        $throughInstance = new $throughModel();

        if (is_null($firstKey)) {
            $firstKey = $this->getClassNameSnakeCase(static::class) . '_id';
        }

        if (is_null($secondKey)) {
            $secondKey = $this->getClassNameSnakeCase($throughModel) . '_id';
        }

        $throughLocalKey = $throughLocalKey ?? $this->getPrimaryKey();
        $relatedLocalKey = $relatedLocalKey ?? $throughInstance->getPrimaryKey();

        return new HasManyThrough($this, $relatedModel, $throughModel, $firstKey, $secondKey, $throughLocalKey, $relatedLocalKey);
    }

    public function morphTo(?string $name = null, ?string $type = null, ?string $id = null): MorphTo
    {
        if (is_null($name)) {
            $name = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        }

        if (is_null($type)) {
            $type = $name . '_type';
        }

        if (is_null($id)) {
            $id = $name . '_id';
        }

        return new MorphTo($this, $type, $id);
    }

    public function morphOne(string $relatedModel, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): MorphOne
    {
        if (is_null($type)) {
            $type = $name . '_type';
        }

        if (is_null($id)) {
            $id = $name . '_id';
        }

        $localKey = $localKey ?? $this->getPrimaryKey();

        return new MorphOne($this, $relatedModel, $id, $localKey, $type, static::class);
    }

    public function morphMany(string $relatedModel, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): MorphMany
    {
        if (is_null($type)) {
            $type = $name . '_type';
        }

        if (is_null($id)) {
            $id = $name . '_id';
        }

        $localKey = $localKey ?? $this->getPrimaryKey();

        return new MorphMany($this, $relatedModel, $id, $localKey, $type, static::class);
    }

    protected function getRelation(string $name): ?BaseRelation
    {
        if (method_exists($this, $name)) {
            $relation = $this->{$name}();

            if ($relation instanceof BaseRelation) {
                return $relation;
            }
        }

        // Check for macros if method doesn't exist or isn't a relation
        if (isset(BaseModel::$macros[static::class][$name])) {
            $macro = BaseModel::$macros[static::class][$name];
            if ($macro instanceof Closure) {
                $relation = $macro->bindTo($this, static::class)();
                if ($relation instanceof BaseRelation) {
                    return $relation;
                }
            }
        }

        return null;
    }

    public function getRelationResults(string $name): mixed
    {
        if (isset($this->relations[$name])) {
            return $this->relations[$name];
        }

        $relation = $this->getRelation($name);

        if (! $relation instanceof BaseRelation) {
            return null;
        }

        $results = $relation->getResults();

        $this->setRelation($name, $results);

        return $results;
    }
}
