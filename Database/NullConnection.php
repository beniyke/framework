<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * A null object implementation of the Connection interface.
 * used for testing or when no database connection is available.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database;

use BadMethodCallException;
use Database\Query\Builder;
use Database\Query\Grammar;
use Database\Query\MySqlGrammar;
use Database\Query\PostgresGrammar;
use Database\Query\SqliteGrammar;
use PDO;
use PDOStatement;
use RuntimeException;

final class NullConnection implements ConnectionInterface
{
    private string $driver;

    public function __construct(string $driver = 'mysql')
    {
        $this->driver = $driver;
    }

    public function connect(): self
    {
        return $this;
    }

    public function options(array $options): self
    {
        return $this;
    }

    public function persistent(bool $isPersistent = true): self
    {
        return $this;
    }

    public function name(string $name): self
    {
        return $this;
    }

    public function getPdo(): PDO
    {
        throw new BadMethodCallException('Cannot access PDO on NullConnection. The real connection is deferred.');
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function isMysql(): bool
    {
        return in_array($this->driver, ['mysql', 'mariadb']);
    }

    public function isPgsql(): bool
    {
        return $this->driver === 'pgsql';
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getConfig(): array
    {
        return [];
    }

    public function newGrammar(): Grammar
    {
        return match ($this->driver) {
            'mysql', 'mariadb' => new MySqlGrammar($this->driver),
            'sqlite' => new SqliteGrammar($this->driver),
            'pgsql' => new PostgresGrammar($this->driver),
            default => throw new RuntimeException("Unsupported driver: {$this->driver}. Implement Grammar."),
        };
    }

    public function table(string $table): Builder
    {
        return new Builder($this, $this->newGrammar(), $table);
    }

    public function execute(string $sql, array $bindings = []): PDOStatement
    {
        throw new BadMethodCallException('Cannot execute SQL on NullConnection. The real connection is deferred.');
    }

    public function select(string $sql, array $bindings = []): array
    {
        return [];
    }

    public function selectOne(string $sql, array $bindings = []): array|false
    {
        return false;
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        return true;
    }

    public function insertGetId(string $sql, array $bindings = []): string
    {
        return '0';
    }

    public function update(string $sql, array $bindings = []): int
    {
        return 0;
    }

    public function delete(string $sql, array $bindings = []): int
    {
        return 0;
    }

    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return 0;
    }

    public function beginTransaction(): bool
    {
        return true;
    }

    public function commit(): void
    {
    }

    public function rollBack(): void
    {
    }

    public function inTransaction(): bool
    {
        return false;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback($this);
    }

    public function afterCommit(callable $callback): void
    {
    }

    public function afterRollback(callable $callback): void
    {
    }

    public function getTables(): array
    {
        return [];
    }

    public function truncateTable(string $table): void
    {
    }

    public function dropAllTables(): void
    {
    }

    public function createDatabase(string $databaseName): void
    {
        throw new BadMethodCallException('createDatabase must be called via DBA admin connection, not NullConnection.');
    }

    public function dropDatabase(string $databaseName): void
    {
        throw new BadMethodCallException('dropDatabase must be called via DBA admin connection, not NullConnection.');
    }

    public function tableExists(string $table): bool
    {
        return false;
    }

    public function columnExists(string $table, string $column): bool
    {
        return false;
    }

    public function disconnect(): void
    {
    }
}
