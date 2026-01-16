<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * MySQL specific schema grammar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Schema\Grammars;

class MysqlGrammar extends SchemaGrammar
{
    protected function compileColumnDefinition(array $command): string
    {
        $dataType = $command['dataType'];
        $definition = "`{$command['column']}` {$dataType}";

        if (isset($command['modifiers']['unsigned'])) {
            $definition .= ' UNSIGNED';
        }

        $definition .= ' ' . ($command['modifiers']['nullable'] ?? 'NOT NULL');

        if (isset($command['modifiers']['default'])) {
            $definition .= ' ' . $command['modifiers']['default'];
        }

        if (isset($command['modifiers']['on_update'])) {
            $definition .= ' ' . $command['modifiers']['on_update'];
        }

        if (isset($command['modifiers']['auto_increment'])) {
            $definition .= ' ' . $command['modifiers']['auto_increment'];
        }

        if (isset($command['modifiers']['comment'])) {
            $escapedComment = str_replace("'", "''", $command['modifiers']['comment']);
            $definition .= " COMMENT '{$escapedComment}'";
        }

        if (isset($command['modifiers']['after'])) {
            $definition .= ' ' . $command['modifiers']['after'];
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
            'FULLTEXT' => 'FULLTEXT INDEX',
            default => 'INDEX',
        };

        return "{$keyType} `{$keyName}` ({$cols})";
    }

    public function compileCreate(string $table, array $commands, array $options): string|array
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
            } elseif ($command['type'] === 'index') {
                $constraints[] = $this->compileIndex($command, 'INDEX');
            } elseif ($command['type'] === 'unique_index') {
                $constraints[] = $this->compileIndex($command, 'UNIQUE_INDEX');
            } elseif ($command['type'] === 'fulltext') {
                $constraints[] = $this->compileIndex($command, 'FULLTEXT');
            } elseif (in_array($command['type'], ['constraint', 'unique_key'])) {
                $constraints[] = $command['definition'];
            }
        }

        $body = implode(', ', array_merge($columnDefinitions, $constraints));

        $properties = [];
        if ($options['engine']) {
            $properties[] = "ENGINE={$options['engine']}";
        }

        if ($options['charset']) {
            $properties[] = "DEFAULT CHARSET={$options['charset']}";
        }

        if ($options['collation']) {
            $properties[] = "COLLATE={$options['collation']}";
        }

        if ($options['comment']) {
            $escapedComment = str_replace("'", "''", $options['comment']);
            $properties[] = "COMMENT='{$escapedComment}'";
        }

        $propertyString = implode(' ', $properties);

        return "CREATE TABLE `{$table}` ({$body}) {$propertyString}";
    }

    public function compileAlter(string $table, array $commands): string|array
    {
        $alterCommands = [];

        foreach ($commands as $command) {
            if (! isset($command['type'])) {
                continue;
            }

            switch ($command['type']) {
                case 'add':
                    $alterCommands[] = 'ADD COLUMN ' . $this->compileColumnDefinition($command);
                    break;
                case 'change':
                    $originalColumn = $command['column'];
                    $definition = $this->compileColumnDefinition($command);
                    $alterCommands[] = "CHANGE COLUMN `{$originalColumn}` `{$command['column']}` {$definition}";
                    break;
                case 'drop':
                    $alterCommands[] = "DROP COLUMN `{$command['column']}`";
                    break;
                case 'rename':
                    $alterCommands[] = "CHANGE COLUMN `{$command['from']}` `{$command['to']}` {$command['definition']}";
                    break;
                case 'add_index':
                    $alterCommands[] = 'ADD ' . $this->compileIndex($command, 'INDEX');
                    break;
                case 'add_unique_index':
                    $alterCommands[] = 'ADD ' . $this->compileIndex($command, 'UNIQUE_INDEX');
                    break;
                case 'add_fulltext':
                    $alterCommands[] = 'ADD ' . $this->compileIndex($command, 'FULLTEXT');
                    break;
                case 'add_constraint':
                    $alterCommands[] = 'ADD ' . $command['definition'];
                    break;
                case 'drop_primary':
                    $alterCommands[] = 'DROP PRIMARY KEY';
                    break;
                case 'drop_constraint':
                    $alterCommands[] = "DROP FOREIGN KEY `{$command['key']}`";
                    break;
                case 'drop_index':
                    $alterCommands[] = "DROP INDEX `{$command['key']}`";
                    break;
                case 'raw':
                    $alterCommands[] = $command['ddl'];
                    break;
            }
        }

        if (empty($alterCommands)) {
            return '';
        }

        return "ALTER TABLE `{$table}` " . implode(', ', $alterCommands);
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
        return "RENAME TABLE `{$from}` TO `{$to}`";
    }

    public function compileTruncate(string $table): string
    {
        return "TRUNCATE TABLE `{$table}`";
    }

    public function compileDropPrimary(string $table, string $name): string
    {
        return "ALTER TABLE `{$table}` DROP PRIMARY KEY";
    }

    public function compileDropIndex(string $table, string $indexName): string
    {
        return "DROP INDEX `{$indexName}` ON `{$table}`";
    }

    public function compileDropForeign(string $table, string $foreignKeyName): string
    {
        return "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKeyName}`";
    }

    public function compileCheckTableExists(string $table): string
    {
        return "SELECT count(*) AS count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$table}'";
    }

    public function compileMigrationsTable(): string
    {
        return 'CREATE TABLE `migrations` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }

    public function compileCheckIndexExists(string $table, string $indexName): string
    {
        return "SELECT COUNT(*) AS count 
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$table}' 
                AND index_name = '{$indexName}'";
    }

    public function compileCheckForeignKeyExists(string $table, string $name): string
    {
        return "SELECT COUNT(*) AS count 
                FROM information_schema.key_column_usage 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$table}' 
                AND constraint_name = '{$name}'";
    }

    public function compileCheckColumnExists(string $table, string $column): string
    {
        return "SELECT COUNT(*) AS count 
                FROM information_schema.columns 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$table}' 
                AND column_name = '{$column}'";
    }
}
