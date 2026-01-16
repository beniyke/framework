<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Base abstract class for Schema Grammars.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Schema\Grammars;

abstract class SchemaGrammar
{
    abstract protected function compileColumnDefinition(array $command): string;

    abstract public function compileCreate(string $table, array $commands, array $options): string|array;

    abstract public function compileAlter(string $table, array $commands): string|array;

    abstract public function compileDrop(string $table): string;

    abstract public function compileDropIfExists(string $table): string;

    abstract public function compileRename(string $from, string $to): string;

    abstract public function compileTruncate(string $table): string;

    abstract public function compileDropPrimary(string $table, string $name): string;

    abstract public function compileDropIndex(string $table, string $indexName): string;

    abstract public function compileDropForeign(string $table, string $foreignKeyName): string;

    abstract public function compileMigrationsTable(): string;

    abstract public function compileCheckTableExists(string $table): string;

    abstract public function compileCheckIndexExists(string $table, string $indexName): string;

    abstract public function compileCheckColumnExists(string $table, string $column): string;

    abstract public function compileCheckForeignKeyExists(string $table, string $foreignKeyName): string;
}
