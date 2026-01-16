<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for soft deleting models.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Traits;

use Database\Query\Builder;
use Helpers\DateTimeHelper;

trait SoftDeletes
{
    public const SOFT_DELETE_COLUMN = 'deleted_at';
    public const SOFT_DELETE_SCOPE = self::class;

    public static function bootSoftDeletes(): void
    {
        $instance = new static();
        if ($instance->usesSoftDeletes() && method_exists(static::class, 'addGlobalScope')) {
            static::addGlobalScope(static::SOFT_DELETE_SCOPE, function (Builder $builder) {
                $builder->where(static::SOFT_DELETE_COLUMN, '=', null);
            });
        }
    }

    public function delete(): bool
    {
        if (! $this->usesSoftDeletes()) {
            if ($this->fireEvent('deleting') === false) {
                return false;
            }

            $key = $this->getPrimaryKey();
            $id = $this->attributes[$key] ?? null;

            if (is_null($id)) {
                return true;
            }

            $result = static::query()
                ->where($key, '=', $id)
                ->delete();

            $this->fireEvent('deleted');

            return (bool) $result;
        }

        if ($this->isTrashed()) {
            return true;
        }

        if ($this->fireEvent('deleting') === false) {
            return false;
        }

        $key = $this->getPrimaryKey();
        $id = $this->attributes[$key] ?? null;

        if (is_null($id)) {
            return true;
        }

        $now = DateTimeHelper::now()->format('Y-m-d H:i:s');
        $timestampValue = $this->castAttributeOnSet(static::SOFT_DELETE_COLUMN, $now);

        $result = static::query()
            ->withoutGlobalScope(static::SOFT_DELETE_SCOPE)
            ->where($key, '=', $id)
            ->update([static::SOFT_DELETE_COLUMN => $timestampValue]);

        if ($result > 0) {
            $this->attributes[static::SOFT_DELETE_COLUMN] = $timestampValue;
            $this->fireEvent('deleted');

            return true;
        }

        return false;
    }

    public function forceDelete(): bool
    {
        if ($this->fireEvent('forceDeleting') === false) {
            return false;
        }

        $key = $this->getPrimaryKey();
        $id = $this->attributes[$key] ?? null;

        if (is_null($id)) {
            return true;
        }

        $result = static::query()
            ->withoutGlobalScope(static::SOFT_DELETE_SCOPE)
            ->where($key, '=', $id)
            ->delete();

        if ($result > 0) {
            unset($this->attributes[$key]);
            $this->fireEvent('forceDeleted');

            return true;
        }

        return false;
    }

    public function restore(): bool
    {
        if ($this->isTrashed() && $this->fireEvent('restoring') === false) {
            return false;
        }

        if (! $this->isTrashed()) {
            return true;
        }

        $key = $this->getPrimaryKey();
        $id = $this->attributes[$key] ?? null;

        if (is_null($id)) {
            return false;
        }

        $result = static::query()
            ->withoutGlobalScope(static::SOFT_DELETE_SCOPE)
            ->where($key, '=', $id)
            ->update([static::SOFT_DELETE_COLUMN => null]);

        if ($result > 0) {
            $this->attributes[static::SOFT_DELETE_COLUMN] = null;
            $this->fireEvent('restored');

            return true;
        }

        return false;
    }

    public function isTrashed(): bool
    {
        return ! is_null($this->attributes[static::SOFT_DELETE_COLUMN] ?? null);
    }

    public function scopeWithTrashed(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope(static::SOFT_DELETE_SCOPE);
    }

    public function scopeOnlyTrashed(Builder $builder): Builder
    {
        return $builder
            ->withoutGlobalScope(static::SOFT_DELETE_SCOPE)
            ->where(static::SOFT_DELETE_COLUMN, '!=', null);
    }

    abstract public function usesSoftDeletes(): bool;

    abstract public function getPrimaryKey(): string;

    abstract protected function fireEvent(string $event): bool;

    abstract public static function query(): Builder;

    abstract protected function castAttributeOnSet(string $key, mixed $value): mixed;

    abstract public static function addGlobalScope(string $identifier, callable $scope): void;
}
