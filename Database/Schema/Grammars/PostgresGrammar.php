<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * PostgreSQL Schema Grammar implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Schema\Grammars;

use RuntimeException;

class PostgresGrammar extends SchemaGrammar
{
    public function compileCreate(string $table, array $commands, array $options): string
    {
        $columnDefinitions = [];
        $constraints = [];

        foreach ($commands as $command) {
            if (! isset($command['type'])) {
                continue;
            }

            if ($command['type'] === 'raw') {
                $columnDefinitions[] = $command['ddl'];
            } elseif ($command['type'] === 'column' && isset($command['dataType'])) {
                $columnDefinitions[] = $this->compileColumnDefinition($command);
            } elseif ($command['type'] === 'primary') {
                $constraints[] = $this->compileIndex('PRIMARY', $command);
            } elseif ($command['type'] === 'unique_index') {
                $constraints[] = $this->compileIndex('UNIQUE_INDEX', $command);
            } elseif ($command['type'] === 'index') {
                // Regular indexes in PostgreSQL are created separately, not in CREATE TABLE
                continue;
            } elseif (in_array($command['type'], ['constraint', 'unique_key'])) {
                $constraints[] = $command['definition'];
            }
        }

        $body = implode(', ', array_merge($columnDefinitions, $constraints));
        $wrappedTable = $this->wrapTable($table);
        $sql = "CREATE TABLE {$wrappedTable} ({$body})";

        if (! empty($options['comment'])) {
            $escapedComment = str_replace("'", "''", $options['comment']);
            $sql .= ";\nCOMMENT ON TABLE {$wrappedTable} IS '{$escapedComment}'";
        }

        return $sql;
    }

    public function compileAlter(string $table, array $commands): array
    {
        $statements = [];
        $table = $this->wrapTable($table);

        foreach ($commands as $command) {
            $type = $command['type'];

            if ($type === 'add') {
                $statements[] = $this->compileAdd($table, $command);
            } elseif ($type === 'drop') {
                $statements[] = $this->compileDropColumn($table, $command);
            } elseif ($type === 'rename') {
                $statements[] = $this->compileRenameColumn($table, $command);
            } elseif ($type === 'change') {
                $statements = array_merge($statements, $this->compileChange($table, $command));
            } elseif ($type === 'drop_constraint') {
                $statements[] = $this->compileDropConstraint($table, $command);
            } elseif ($type === 'drop_index') {
                $statements[] = $this->compileDropIndexCommand($table, $command);
            } else {
                throw new RuntimeException("PostgresGrammar does not support DDL type: {$type} in ALTER operations.");
            }
        }

        return array_filter($statements);
    }

    protected function compileColumnDefinition(array $command): string
    {
        $sql = $this->wrap($command['column']) . ' ' . $this->getDataType($command['dataType']);

        if ($command['dataType'] === 'BIGINT' && in_array('AUTO_INCREMENT', $command['modifiers'], true)) {
            $sql = $this->wrap($command['column']) . ' BIGSERIAL';
        }

        $sql .= $this->compileModifiers($command);

        return $sql;
    }

    protected function compileColumn(array $command): string
    {
        return $this->compileColumnDefinition($command);
    }

    protected function compileDataType(string $dataType): string
    {
        return match (strtoupper($dataType)) {
            'TINYINT(1)', 'BOOLEAN' => 'BOOLEAN',
            'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'TEXT',
            'TINYINT' => 'SMALLINT',
            'MEDIUMINT' => 'INTEGER',
            'INT' => 'INTEGER',
            'BIGINT' => 'BIGINT',
            'DATETIME' => 'TIMESTAMP(0) WITHOUT TIME ZONE',
            'TIMESTAMP' => 'TIMESTAMP WITHOUT TIME ZONE',
            'JSON' => 'JSONB',
            default => $dataType,
        };
    }

    protected function compileIndex(string $type, array $command): string
    {
        $keyType = match (strtoupper($type)) {
            'PRIMARY' => 'PRIMARY KEY',
            'UNIQUE_INDEX' => 'UNIQUE',
            default => 'INDEX',
        };

        $columns = $this->columnize($command['columns']);
        $key = $this->wrap($command['key']);

        if ($type === 'PRIMARY') {
            return "CONSTRAINT {$key} PRIMARY KEY ({$columns})";
        }

        return match ($keyType) {
            'UNIQUE' => "CONSTRAINT {$key} UNIQUE ({$columns})",
            default => '',
        };
    }

    protected function compileAddIndex(string $table, array $command): string
    {
        $columns = $this->columnize($command['columns']);
        $key = $this->wrap($command['key']);

        if (strtoupper($command['type']) === 'FULLTEXT') {
            throw new RuntimeException('PostgreSQL FullText indexing is complex and not supported by this simple grammar. Use a raw command with a GIN index on to_tsvector().');
        }

        return "CREATE INDEX {$key} ON {$table} ({$columns})";
    }

    protected function compileAddUniqueIndex(string $table, array $command): string
    {
        $columns = $this->columnize($command['columns']);
        $key = $this->wrap($command['key']);

        return "ALTER TABLE {$table} ADD CONSTRAINT {$key} UNIQUE ({$columns})";
    }

    protected function compileConstraint(array $command): string
    {
        return $command['definition'];
    }

    protected function compileAdd(string $table, array $command): string
    {
        $columnSql = $this->compileColumn($command);

        return "ALTER TABLE {$table} ADD COLUMN {$columnSql}";
    }

    protected function compileChange(string $table, array $command): array
    {
        $statements = [];
        $column = $this->wrap($command['column']);
        $dataType = $this->getDataType($command['dataType']);

        $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$column} TYPE {$dataType}";

        if (isset($command['modifiers']['nullable'])) {
            $nullable = $command['modifiers']['nullable'];
            if ($nullable === 'NULL') {
                $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL";
            } else {
                $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL";
            }
        }

        if (isset($command['modifiers']['default'])) {
            $default = trim(str_replace('DEFAULT', '', $command['modifiers']['default']));
            if ($default === 'NULL' || $default === 'null') {
                $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT";
            } else {
                $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$default}";
            }
        }

        if (isset($command['modifiers']['comment'])) {
            $comment = $command['modifiers']['comment'];
            $statements[] = "COMMENT ON COLUMN {$table}.{$column} IS '{$comment}'";
        }

        return $statements;
    }

    public function compileDrop(string $table): string
    {
        return 'DROP TABLE ' . $this->wrapTable($table);
    }

    protected function compileDropColumn(string $table, array $command): string
    {
        return "ALTER TABLE {$table} DROP COLUMN " . $this->wrap($command['column']);
    }

    public function compileRename(string $from, string $to): string
    {
        return 'ALTER TABLE ' . $this->wrapTable($from) . ' RENAME TO ' . $this->wrapTable($to);
    }

    protected function compileRenameColumn(string $table, array $command): string
    {
        $from = $this->wrap($command['from']);
        $to = $this->wrap($command['to']);

        return "ALTER TABLE {$table} RENAME COLUMN {$from} TO {$to}";
    }

    public function compileDropIndex(string $table, string $indexName): string
    {
        return 'DROP INDEX ' . $this->wrap($indexName);
    }

    protected function compileDropIndexCommand(string $table, array $command): string
    {
        return 'DROP INDEX ' . $this->wrap($command['key']);
    }

    public function compileDropPrimary(string $table, string $name): string
    {
        $wrappedTable = $this->wrapTable($table);

        return "ALTER TABLE {$wrappedTable} DROP CONSTRAINT " . $this->wrap($name);
    }

    protected function compileDropConstraint(string $table, array $command): string
    {
        return "ALTER TABLE {$table} DROP CONSTRAINT " . $this->wrap($command['key']);
    }

    protected function compileTableComment(string $table, string $comment): string
    {
        $table = $this->wrapTable($table);

        return "COMMENT ON TABLE {$table} IS '{$comment}'";
    }

    protected function compileModifiers(array $command): string
    {
        $sql = '';

        if (isset($command['modifiers']['nullable']) && $command['modifiers']['nullable'] === 'NOT NULL') {
            $sql .= ' NOT NULL';
        }

        if (isset($command['modifiers']['default'])) {
            $sql .= ' ' . $command['modifiers']['default'];
        }

        return $sql;
    }

    public function compileDropIfExists(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrapTable($table);
    }

    public function compileTruncate(string $table): string
    {
        return 'TRUNCATE TABLE ' . $this->wrapTable($table) . ' RESTART IDENTITY';
    }

    public function compileDropForeign(string $table, string $foreignKeyName): string
    {
        $wrappedTable = $this->wrapTable($table);

        return "ALTER TABLE {$wrappedTable} DROP CONSTRAINT " . $this->wrap($foreignKeyName);
    }

    public function compileCheckTableExists(string $table): string
    {
        return "SELECT count(*) AS count FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = '{$table}'";
    }

    public function compileMigrationsTable(): string
    {
        return 'CREATE TABLE migrations (
            id BIGSERIAL PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INTEGER NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )';
    }

    public function compileCheckIndexExists(string $table, string $indexName): string
    {
        return "SELECT COUNT(*) AS count 
                FROM pg_indexes 
                WHERE schemaname = current_schema() 
                AND tablename = '{$table}' 
                AND indexname = '{$indexName}'";
    }

    public function compileCheckForeignKeyExists(string $table, string $name): string
    {
        return "SELECT COUNT(*) as count 
                FROM information_schema.table_constraints 
                WHERE table_name = '{$table}' 
                AND constraint_name = '{$name}'";
    }

    public function compileCheckColumnExists(string $table, string $column): string
    {
        return "SELECT COUNT(*) as count 
                FROM information_schema.columns 
                WHERE table_name = '{$table}' 
                AND column_name = '{$column}'";
    }

    protected function wrap(string $value): string
    {
        return '\"' . $value . '\"';
    }

    protected function wrapTable(string $table): string
    {
        return '\"' . $table . '\"';
    }

    protected function columnize(array $columns): string
    {
        return implode(', ', array_map(fn ($col) => $this->wrap($col), $columns));
    }

    protected function getDataType(string $dataType): string
    {
        return $this->compileDataType($dataType);
    }
}
