<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Schema builder and manager.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Schema;

use Database\ConnectionInterface;
use Database\Schema\Traits\SchemaGrammarResolver;
use RuntimeException;

class Schema
{
    use SchemaGrammarResolver;

    protected static ?ConnectionInterface $connection = null;

    public static function setConnection(ConnectionInterface $connection): void
    {
        static::$connection = $connection;
    }

    protected static function getConnection(): ConnectionInterface
    {
        if (static::$connection === null) {
            throw new RuntimeException('Database connection not set for Schema operations.');
        }

        return static::$connection;
    }

    public static function create(string $table, callable $callback): void
    {
        $builder = new SchemaBuilder(static::getConnection(), $table, 'create');
        $callback($builder);
        $builder->execute();
    }

    public static function createIfNotExist(string $table, callable $callback): void
    {
        if (! static::hasTable($table)) {
            static::create($table, $callback);
        }
    }

    public static function table(string $table, callable $callback): void
    {
        $builder = new SchemaBuilder(static::getConnection(), $table, 'alter');
        $callback($builder);
        $builder->execute();
    }

    public static function tableIfExist(string $table, callable $callback): void
    {
        if (static::hasTable($table)) {
            static::table($table, $callback);
        }
    }

    public static function drop(string $table): void
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);
        $sql = $grammar->compileDrop($table);
        $connection->statement($sql);
    }

    public static function dropIfExists(string $table): void
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);
        $sql = $grammar->compileDropIfExists($table);
        $connection->statement($sql);
    }

    public static function rename(string $from, string $to): void
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);
        $sql = $grammar->compileRename($from, $to);
        $connection->statement($sql);
    }

    public static function truncate(string $table): void
    {
        $connection = static::getConnection();
        $connection->truncateTable($table);
    }

    public static function dropPrimary(string $table, string $name = 'PRIMARY'): void
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);
        $sql = $grammar->compileDropPrimary($table, $name);
        $connection->statement($sql);
    }

    public static function dropUnique(string $table, string $name): void
    {
        static::dropIndex($table, $name);
    }

    public static function dropIndex(string $table, string $name): void
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);
        $sql = $grammar->compileDropIndex($table, $name);
        $connection->statement($sql);
    }

    public static function dropForeign(string $table, string $name): void
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);
        $sql = $grammar->compileDropForeign($table, $name);
        $connection->statement($sql);
    }

    public static function hasTable(string $table): bool
    {
        return static::getConnection()->tableExists($table);
    }

    public static function hasIndex(string $table, string $indexName): bool
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);
        $sql = $grammar->compileCheckIndexExists($table, $indexName);
        $result = $connection->selectOne($sql);

        return ($result['count'] ?? 0) > 0;
    }

    public static function whenTableHasIndex(string $table, string $indexName, callable $callback): void
    {
        if (static::hasIndex($table, $indexName)) {
            static::table($table, $callback);
        }
    }

    public static function whenTableDoesntHaveIndex(string $table, string $indexName, callable $callback): void
    {
        if (! static::hasIndex($table, $indexName)) {
            static::table($table, $callback);
        }
    }

    public static function hasUnique(string $table, string $name): bool
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);
        $sql = $grammar->compileCheckIndexExists($table, $name); // Most DBs treat unique keys as indexes
        $result = $connection->selectOne($sql);

        return ($result['count'] ?? 0) > 0;
    }

    public static function hasUniqueKey(string $table, string $name): bool
    {
        return static::hasUnique($table, $name);
    }

    public static function hasForeignKey(string $table, string $name): bool
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);
        $sql = $grammar->compileCheckForeignKeyExists($table, $name);
        $result = $connection->selectOne($sql);

        return ($result['count'] ?? 0) > 0;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);
        $sql = $grammar->compileCheckColumnExists($table, $column);
        $result = $connection->selectOne($sql);

        return ($result['count'] ?? 0) > 0;
    }

    public static function whenTableHasColumn(string $table, string $column, callable $callback): void
    {
        if (static::hasColumn($table, $column)) {
            static::table($table, $callback);
        }
    }

    public static function whenTableDoesntHaveColumn(string $table, string $column, callable $callback): void
    {
        if (! static::hasColumn($table, $column)) {
            static::table($table, $callback);
        }
    }

    public static function dropForeignIfExists(string $table, string $name): void
    {
        if (static::hasForeignKey($table, $name)) {
            static::dropForeign($table, $name);
        }
    }

    public static function createMigrationsTable(): void
    {
        $connection = static::getConnection();
        $grammar = (new static())->getGrammar($connection);

        $checkSql = $grammar->compileCheckTableExists('migrations');
        $exists = $connection->selectOne($checkSql);

        if (! ($exists['count'] ?? 0)) {
            $sql = $grammar->compileMigrationsTable();
            $statements = is_array($sql) ? $sql : [$sql];
            foreach ($statements as $statement) {
                if (! empty($statement)) {
                    $connection->statement($statement);
                }
            }
        }
    }

    public static function whenDriverIs(string|array $drivers, callable $callback): void
    {
        $drivers = (array) $drivers;
        if (in_array(static::getConnection()->getDriver(), $drivers)) {
            $callback();
        }
    }

    public static function whenDriverIsNot(string|array $drivers, callable $callback): void
    {
        $drivers = (array) $drivers;
        if (! in_array(static::getConnection()->getDriver(), $drivers)) {
            $callback();
        }
    }
}
