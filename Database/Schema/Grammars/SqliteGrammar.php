<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * SQLite Schema Grammar implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Schema\Grammars;

use RuntimeException;

class SqliteGrammar extends SchemaGrammar
{
    protected string $table = '';

    protected function compileColumnDefinition(array $command): string
    {
        $dataType = $command['dataType'];

        if (str_contains($dataType, 'INT') || str_contains($dataType, 'TIMESTAMP') || str_contains($dataType, 'YEAR')) {
            $dataType = 'INTEGER';
        } elseif (str_contains($dataType, 'TEXT') || str_contains($dataType, 'VARCHAR') || str_contains($dataType, 'JSON') || str_contains($dataType, 'ENUM') || str_contains($dataType, 'DATETIME') || str_contains($dataType, 'DATE') || str_contains($dataType, 'TIME')) {
            $dataType = 'TEXT';
        } elseif (str_contains($dataType, 'DECIMAL') || str_contains($dataType, 'FLOAT') || str_contains($dataType, 'DOUBLE')) {
            $dataType = 'REAL';
        }

        $definition = "`{$command['column']}` {$dataType}";

        $definition .= ' ' . ($command['modifiers']['nullable'] ?? 'NOT NULL');

        if (isset($command['modifiers']['default'])) {
            $definition .= ' ' . $command['modifiers']['default'];
        }

        return $definition;
    }

    protected function compileIndex(array $command, string $type): string
    {
        $cols = '`' . implode('`, `', $command['columns']) . '`';
        $keyName = $command['key'];

        $keyType = match ($type) {
            'INDEX' => 'INDEX',
            'UNIQUE_INDEX' => 'UNIQUE INDEX',
            default => 'INDEX',
        };

        return "CREATE {$keyType} `{$keyName}` ON `{$this->table}` ({$cols})";
    }

    public function compileCreate(string $table, array $commands, array $options): string|array
    {
        $columnDefinitions = [];
        $constraints = [];
        $indexStatements = [];
        $this->table = $table;

        $hasPrimaryKey = false;

        foreach ($commands as $command) {
            if (! isset($command['type'])) {
                continue;
            }

            if ($command['type'] === 'raw') {
                $columnDefinitions[] = $command['ddl'];
            } elseif ($command['type'] === 'column' && isset($command['dataType'])) {
                $definition = $this->compileColumnDefinition($command);

                if (isset($command['modifiers']['auto_increment']) && str_contains($command['dataType'], 'INT')) {
                    $definition = str_replace('NOT NULL', 'PRIMARY KEY AUTOINCREMENT NOT NULL', $definition);
                    $hasPrimaryKey = true;
                }
                $columnDefinitions[] = $definition;
            } elseif ($command['type'] === 'index') {
                $indexStatements[] = $this->compileIndex($command, 'INDEX');
            } elseif ($command['type'] === 'unique_index') {
                $indexStatements[] = $this->compileIndex($command, 'UNIQUE_INDEX');
            } elseif ($command['type'] === 'fulltext') {
            } elseif (in_array($command['type'], ['constraint', 'unique_key'])) {
                if ($hasPrimaryKey && str_contains($command['definition'], 'PRIMARY KEY')) {
                    continue;
                }
                $constraints[] = $command['definition'];
            }
        }

        $body = implode(', ', array_merge($columnDefinitions, $constraints));

        $createSql = "CREATE TABLE `{$table}` ({$body})";

        return array_merge([$createSql], $indexStatements);
    }

    public function compileAlter(string $table, array $commands): string|array
    {
        $simpleAlterCommands = [];
        $indexCommands = [];
        $unsupportedOperations = [];
        $this->table = $table;

        foreach ($commands as $command) {
            if (! isset($command['type'])) {
                continue;
            }

            switch ($command['type']) {
                case 'add':
                    $simpleAlterCommands[] = 'ADD COLUMN ' . $this->compileColumnDefinition($command);
                    break;
                case 'add_index':
                    $indexCommands[] = $this->compileIndex($command, 'INDEX');
                    break;
                case 'add_unique_index':
                    $indexCommands[] = $this->compileIndex($command, 'UNIQUE_INDEX');
                    break;
                case 'raw':
                    $simpleAlterCommands[] = $command['ddl'];
                    break;
                case 'drop_index':
                case 'drop_unique':
                    $indexCommands[] = "DROP INDEX `{$command['key']}`";
                    break;
                case 'change':
                case 'rename':
                case 'drop':
                case 'drop_primary':
                case 'drop_constraint':
                case 'add_constraint':
                case 'add_unique_key':
                case 'add_fulltext':
                    $unsupportedOperations[] = $command['type'];
                    break;
            }
        }

        if (! empty($unsupportedOperations)) {
            $msg = 'Unsupported SQLite ALTER operation(s): ' . implode(', ', array_unique($unsupportedOperations)) . '. Complex changes require a manual table rebuild.';
            throw new RuntimeException($msg);
        }

        $statements = [];
        if (! empty($simpleAlterCommands)) {
            foreach ($simpleAlterCommands as $alterCommand) {
                $statements[] = "ALTER TABLE `{$table}` {$alterCommand}";
            }
        }

        return array_merge($statements, $indexCommands);
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE `{$table}`";
    }

    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS `{$table}`";
    }

    public function compileRename(string $from, string $to): string
    {
        return "ALTER TABLE `{$from}` RENAME TO `{$to}`";
    }

    public function compileTruncate(string $table): string
    {
        return "DELETE FROM `{$table}`";
    }

    public function compileDropIndex(string $table, string $indexName): string
    {
        return "DROP INDEX `{$indexName}`";
    }

    public function compileDropPrimary(string $table, string $name): string
    {
        throw new RuntimeException('Dropping the primary key is not supported by SQLite.');
    }

    public function compileDropForeign(string $table, string $foreignKeyName): string
    {
        throw new RuntimeException('Dropping foreign keys is not supported by SQLite.');
    }

    public function compileCheckTableExists(string $table): string
    {
        return "SELECT count(*) AS count FROM sqlite_master WHERE type='table' AND name='{$table}'";
    }

    public function compileMigrationsTable(): string
    {
        return 'CREATE TABLE `migrations` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `migration` TEXT NOT NULL UNIQUE,
            `batch` INTEGER NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        )';
    }

    public function compileCheckIndexExists(string $table, string $indexName): string
    {
        return "SELECT COUNT(*) AS count 
                FROM sqlite_master 
                WHERE type = 'index' 
                AND tbl_name = '{$table}' 
                AND name = '{$indexName}'";
    }

    public function compileCheckForeignKeyExists(string $table, string $name): string
    {
        return "SELECT COUNT(*) as count FROM pragma_foreign_key_list('{$table}') WHERE \"from\" = '{$name}' OR \"to\" = '{$name}'";
    }

    public function compileCheckColumnExists(string $table, string $column): string
    {
        return "SELECT COUNT(*) as count FROM pragma_table_info('{$table}') WHERE name = '{$column}'";
    }
}
