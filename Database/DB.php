<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * DB acts as a facade for the database connection, providing static access
 * to query building, transaction management, and connection handling methods.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 *
 * @see Connection
 */

namespace Database;

use Core\Services\ConfigServiceInterface;
use Database\Query\Builder;
use Database\Query\RawExpression;
use Exception;
use RuntimeException;

final class DB
{
    private static array $connections = [];

    private static string $defaultConnectionName = 'default';

    private static ?array $slowQueryListener = null;

    public static function setDefaultConnection(?ConnectionInterface $connection, string $name = 'default'): void
    {
        if ($connection === null) {
            unset(self::$connections[$name]);

            return;
        }
        self::$connections[$name] = $connection;
        self::$defaultConnectionName = $name;
    }

    public static function addConnection(string $name, ConnectionInterface $connection): void
    {
        self::$connections[$name] = $connection;
    }

    public static function connection(?string $name = null): ConnectionInterface
    {
        $name = $name ?: self::$defaultConnectionName;

        if (! isset(self::$connections[$name])) {
            try {
                $config = resolve(ConfigServiceInterface::class);
                $dbConfig = $config->get("database.connections.{$name}");

                if ($dbConfig) {
                    $connConfig = ConnectionConfig::fromSingleConfig($dbConfig);
                    self::$connections[$name] = ConnectionFactory::create($connConfig)->name($name);
                }
            } catch (Exception $e) {
                // If resolution fails, we fall through to the exception below
            }

            if (! isset(self::$connections[$name])) {
                throw new RuntimeException("Database connection [{$name}] not found. Call DB::setDefaultConnection() or configure it in database.php first.");
            }
        }

        return self::$connections[$name];
    }

    private static function getConnection(): ConnectionInterface
    {
        return self::connection();
    }

    public static function whenQueryingForLongerThan(float $seconds, callable $callback): void
    {
        self::$slowQueryListener = [
            'threshold_ms' => $seconds * 1000,
            'callback' => $callback,
        ];
    }

    public static function getSlowQueryListener(): ?array
    {
        return self::$slowQueryListener;
    }

    public static function afterCommit(callable $callback): void
    {
        self::getConnection()->afterCommit($callback);
    }

    public static function afterRollback(callable $callback): void
    {
        self::getConnection()->afterRollback($callback);
    }

    public static function table(string $table): Builder
    {
        return self::getConnection()->table($table);
    }

    public static function select(string $table, array $columns = ['*']): array
    {
        return self::table($table)->select($columns)->get();
    }

    public static function insert(string $table, array $values): bool
    {
        return self::table($table)->insert($values);
    }

    public static function insertOrIgnore(string $table, array $values): int
    {
        return self::table($table)->insertOrIgnore($values);
    }

    public static function insertGetId(string $table, array $values): int|string
    {
        return self::table($table)->insertGetId($values);
    }

    public static function update(string $table, array $values): int
    {
        return self::table($table)->update($values);
    }

    public static function delete(string $table): int
    {
        return self::table($table)->delete();
    }

    public static function count(string $table): int
    {
        return self::table($table)->count();
    }

    public static function transaction(callable $callback): mixed
    {
        return self::getConnection()->transaction($callback);
    }

    public static function statement(string $sql, array $bindings = []): bool
    {
        return self::getConnection()->statement($sql, $bindings);
    }

    public static function raw(string $expression, array $bindings = []): RawExpression
    {
        return new RawExpression($expression, $bindings);
    }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        if (method_exists(self::class, $method)) {
            return self::{$method}(...$parameters);
        }

        return self::table($method);
    }
}
