<?php

declare(strict_types=1);

namespace Database;

use Database\Query\Builder;
use Database\Query\Grammar;
use PDO;
use PDOStatement;

/**
 * Anchor Framework
 *
 * ConnectionInterface defines the contract for all database connection drivers.
 * It ensures consistent methods for querying, transaction management, and schema operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */
interface ConnectionInterface
{
    public function connect(): self;

    public function options(array $options): self;

    public function persistent(bool $isPersistent = true): self;

    public function name(string $name): self;

    public function getPdo(): PDO;

    public function getDriver(): string;

    public function isSqlite(): bool;

    public function isMysql(): bool;

    public function isPgsql(): bool;

    public function getConfig(): array;

    public function newGrammar(): Grammar;

    public function table(string $table): Builder;

    public function execute(string $sql, array $bindings = []): PDOStatement;

    public function select(string $sql, array $bindings = []): array;

    public function selectOne(string $sql, array $bindings = []): array|false;

    public function statement(string $sql, array $bindings = []): bool;

    public function insertGetId(string $sql, array $bindings = []): string;

    public function update(string $sql, array $bindings = []): int;

    public function delete(string $sql, array $bindings = []): int;

    public function beginTransaction(): bool;

    public function commit(): void;

    public function rollBack(): void;

    public function inTransaction(): bool;

    public function transaction(callable $callback);

    public function afterCommit(callable $callback): void;

    public function afterRollback(callable $callback): void;

    public function tableExists(string $table): bool;

    public function columnExists(string $table, string $column): bool;

    public function getTables(): array;

    public function truncateTable(string $table): void;

    public function dropAllTables(): void;

    public function createDatabase(string $databaseName): void;

    public function dropDatabase(string $databaseName): void;

    public function disconnect(): void;
}
