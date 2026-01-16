<?php

declare(strict_types=1);

namespace Testing\Concerns;

use Database\ConnectionInterface;
use Database\DB;
use PHPUnit\Framework\Assert as PHPUnit;

trait InteractsWithDatabase
{
    /**
     * @var ConnectionInterface|null
     */
    protected ?ConnectionInterface $connection = null;

    /**
     * Assert that a table in the database contains the given data.
     */
    protected function assertDatabaseHas(string $table, array $data, ?string $connection = null): self
    {
        $query = DB::connection($connection)->table($table);

        foreach ($data as $key => $value) {
            $query->where($key, '=', $value);
        }

        PHPUnit::assertTrue(
            $query->count() > 0,
            "Failed asserting that table [{$table}] has the given data: " . json_encode($data)
        );

        return $this;
    }

    /**
     * Assert that a table in the database does not contain the given data.
     */
    protected function assertDatabaseMissing(string $table, array $data, ?string $connection = null): self
    {
        $query = DB::connection($connection)->table($table);

        foreach ($data as $key => $value) {
            $query->where($key, '=', $value);
        }

        PHPUnit::assertEquals(
            0,
            $query->count(),
            "Failed asserting that table [{$table}] does not contain the given data: " . json_encode($data)
        );

        return $this;
    }

    /**
     * Assert that the database table has the expected number of records.
     */
    protected function assertDatabaseCount(string $table, int $count, ?string $connection = null): self
    {
        PHPUnit::assertEquals(
            $count,
            DB::connection($connection)->table($table)->count(),
            "Failed asserting that table [{$table}] has {$count} records."
        );

        return $this;
    }

    protected function getConnection(?string $connection = null): ConnectionInterface
    {
        return DB::connection($connection);
    }

    /**
     * Assert that a record has been soft deleted.
     */
    protected function assertSoftDeleted(string $table, array $data, string $deletedAtColumn = 'deleted_at', ?string $connection = null): self
    {
        $query = DB::connection($connection)->table($table);

        foreach ($data as $key => $value) {
            $query->where($key, '=', $value);
        }

        $query->whereNotNull($deletedAtColumn);

        PHPUnit::assertTrue(
            $query->count() > 0,
            "Failed asserting that table [{$table}] has the soft deleted record: " . json_encode($data)
        );

        return $this;
    }

    /**
     * Assert that a record has not been soft deleted.
     */
    protected function assertNotSoftDeleted(string $table, array $data, string $deletedAtColumn = 'deleted_at', ?string $connection = null): self
    {
        $query = DB::connection($connection)->table($table);

        foreach ($data as $key => $value) {
            $query->where($key, '=', $value);
        }

        $query->whereNull($deletedAtColumn);

        PHPUnit::assertTrue(
            $query->count() > 0,
            "Failed asserting that table [{$table}] has the non-soft-deleted record: " . json_encode($data)
        );

        return $this;
    }

    /**
     * Assert that a model exists in the database.
     *
     * @param object $model Must have getTable(), getKeyName(), and getKey() methods.
     */
    protected function assertModelExists(object $model, ?string $connection = null): self
    {
        $table = $model->getTable();
        $key = $model->getKeyName();
        $value = $model->getKey();

        PHPUnit::assertTrue(
            DB::connection($connection)->table($table)->where($key, '=', $value)->count() > 0,
            "Failed asserting that model [{$table}] with key [{$value}] exists in the database."
        );

        return $this;
    }

    /**
     * Assert that a model does not exist in the database.
     *
     * @param object $model Must have getTable(), getKeyName(), and getKey() methods.
     */
    protected function assertModelMissing(object $model, ?string $connection = null): self
    {
        $table = $model->getTable();
        $key = $model->getKeyName();
        $value = $model->getKey();

        PHPUnit::assertEquals(
            0,
            DB::connection($connection)->table($table)->where($key, '=', $value)->count(),
            "Failed asserting that model [{$table}] with key [{$value}] is missing from the database."
        );

        return $this;
    }

    /**
     * Alias for assertDatabaseMissing - useful for semantic clarity.
     */
    protected function assertDeleted(string $table, array $data, ?string $connection = null): self
    {
        return $this->assertDatabaseMissing($table, $data, $connection);
    }
}
