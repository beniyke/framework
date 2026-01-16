<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Builder class for creating and modifying database schemas.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Schema;

use BackedEnum;
use Core\Support\Environment;
use Database\ConnectionInterface;
use Database\DB;
use Database\Query\RawExpression;
use Database\Schema\Traits\SchemaGrammarResolver;
use InvalidArgumentException;
use RuntimeException;

class SchemaBuilder
{
    use SchemaGrammarResolver;

    protected ConnectionInterface $connection;

    protected string $table;

    protected string $operation;

    protected array $commands = [];

    protected array $lastCommand = [];

    protected array $currentForeignKey = [];

    protected string $engine = 'InnoDB';

    protected ?string $charset = null;

    protected ?string $collation = null;

    protected ?string $tableComment = null;

    public function __construct(ConnectionInterface $connection, string $table, string $operation)
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->operation = $operation;
    }

    public function engine(string $engine): self
    {
        if ($this->operation !== 'create') {
            throw new RuntimeException('engine() can only be set during Schema::create().');
        }

        $this->engine = $engine;

        return $this;
    }

    public function comment(string $comment): self
    {
        if ($this->operation !== 'create') {
            throw new RuntimeException('comment() can only be set during Schema::create().');
        }

        $this->tableComment = $comment;

        return $this;
    }

    public function charset(string $charset): self
    {
        if ($this->operation !== 'create') {
            throw new RuntimeException('charset() can only be set during Schema::create().');
        }

        $this->charset = $charset;

        return $this;
    }

    public function collation(string $collation): self
    {
        if ($this->operation !== 'create') {
            throw new RuntimeException('collation() can only be set during Schema::create().');
        }

        $this->collation = $collation;

        return $this;
    }

    public function raw(string $sql): self
    {
        $this->commands[] = ['type' => 'raw', 'ddl' => $sql];
        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    protected function addColumn(string $column, string $type): self
    {
        $commandType = $this->operation === 'create' ? 'column' : 'add';

        $command = [
            'type' => $commandType,
            'column' => $column,
            'dataType' => $type,
            'modifiers' => [],
        ];

        $this->commands[] = $command;
        $this->lastCommand = &$this->commands[count($this->commands) - 1];

        return $this;
    }

    public function id(string $column = 'id'): self
    {
        return $this->bigInteger($column)
            ->unsigned()
            ->autoIncrement()
            ->primary($column);
    }

    public function string(string $column, int $length = 255): self
    {
        return $this->addColumn($column, "VARCHAR({$length})");
    }

    public function tinyText(string $column): self
    {
        return $this->addColumn($column, 'TINYTEXT');
    }

    public function text(string $column): self
    {
        return $this->addColumn($column, 'TEXT');
    }

    public function mediumText(string $column): self
    {
        return $this->addColumn($column, 'MEDIUMTEXT');
    }

    public function longText(string $column): self
    {
        return $this->addColumn($column, 'LONGTEXT');
    }

    public function tinyInteger(string $column): self
    {
        return $this->addColumn($column, 'TINYINT');
    }

    public function smallInteger(string $column): self
    {
        return $this->addColumn($column, 'SMALLINT');
    }

    public function mediumInteger(string $column): self
    {
        return $this->addColumn($column, 'MEDIUMINT');
    }

    public function integer(string $column): self
    {
        return $this->addColumn($column, 'INT');
    }

    public function bigInteger(string $column): self
    {
        return $this->addColumn($column, 'BIGINT');
    }

    public function binary(string $column): self
    {
        return $this->addColumn($column, 'BLOB');
    }

    public function uuid(string $column): self
    {
        return $this->addColumn($column, 'UUID');
    }

    public function ipAddress(string $column): self
    {
        return $this->addColumn($column, 'VARCHAR(45)');
    }

    public function unsignedBigInteger(string $column): self
    {
        return $this->bigInteger($column)->unsigned();
    }

    public function unsignedTinyInteger(string $column): self
    {
        return $this->tinyInteger($column)->unsigned();
    }

    public function unsignedSmallInteger(string $column): self
    {
        return $this->smallInteger($column)->unsigned();
    }

    public function unsignedInteger(string $column): self
    {
        return $this->integer($column)->unsigned();
    }

    public function decimal(string $column, int $precision = 8, int $scale = 2): self
    {
        return $this->addColumn($column, "DECIMAL({$precision}, {$scale})");
    }

    public function float(string $column, int $precision = 8, int $scale = 2): self
    {
        return $this->addColumn($column, "FLOAT({$precision}, {$scale})");
    }

    public function double(string $column, int $precision = 15, int $scale = 8): self
    {
        return $this->addColumn($column, "DOUBLE({$precision}, {$scale})");
    }

    public function dateTime(string $column): self
    {
        return $this->addColumn($column, 'DATETIME');
    }

    public function timestamp(string $column): self
    {
        return $this->addColumn($column, 'TIMESTAMP');
    }

    public function date(string $column): self
    {
        return $this->addColumn($column, 'DATE');
    }

    public function time(string $column): self
    {
        return $this->addColumn($column, 'TIME');
    }

    public function year(string $column): self
    {
        return $this->addColumn($column, 'YEAR');
    }

    public function boolean(string $column): self
    {
        return $this->addColumn($column, 'TINYINT(1)');
    }

    public function json(string $column): self
    {
        return $this->addColumn($column, 'JSON');
    }

    public function enum(string $column, array|string $allowedValues): self
    {
        if (is_string($allowedValues) && enum_exists($allowedValues)) {
            /** @var BackedEnum $allowedValues */
            $allowedValues = array_map(fn ($case) => $case->value, $allowedValues::cases());
        }

        $quotedValues = array_map(fn ($value) => "'{$value}'", (array) $allowedValues);
        $typeDefinition = 'ENUM(' . implode(', ', $quotedValues) . ')';

        return $this->addColumn($column, $typeDefinition);
    }

    /**
     * Corrected to prevent the reference from deleting the 'updated_at' command.
     */
    public function timestamps(): self
    {
        $this->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
        $this->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'))->onUpdateRaw(DB::raw('CURRENT_TIMESTAMP'));

        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function dateTimestamps(): self
    {
        $this->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
        $this->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'))->onUpdateRaw(DB::raw('CURRENT_TIMESTAMP'));

        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function softDeletes(): self
    {
        $this->dateTime('deleted_at')->nullable();
        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function softDeletesTz(): self
    {
        $this->timestamp('deleted_at')->nullable();
        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    protected function ensureLastCommandIsColumn(): void
    {
        if (empty($this->lastCommand) || ! isset($this->lastCommand['dataType'])) {
            throw new RuntimeException('Modifier must follow a valid column definition.');
        }
    }

    public function unsigned(): self
    {
        $this->ensureLastCommandIsColumn();
        $this->lastCommand['modifiers']['unsigned'] = 'UNSIGNED';

        return $this;
    }

    public function columnComment(string $comment): self
    {
        $this->ensureLastCommandIsColumn();
        $this->lastCommand['modifiers']['comment'] = $comment;

        return $this;
    }

    public function change(): self
    {
        if ($this->operation !== 'alter') {
            throw new RuntimeException('change() can only be used with Schema::table().');
        }

        $this->ensureLastCommandIsColumn();
        $this->lastCommand['type'] = 'change';

        return $this;
    }

    public function nullable(bool $nullable = true): self
    {
        $this->ensureLastCommandIsColumn();

        if ($nullable) {
            $this->lastCommand['modifiers']['nullable'] = 'NULL';
        } else {
            $this->lastCommand['modifiers']['nullable'] = 'NOT NULL';
        }

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->ensureLastCommandIsColumn();

        if ($value instanceof RawExpression) {
            $value = $value->getExpression();
        } elseif (is_string($value) && strtoupper($value) !== 'NULL') {
            $value = "'{$value}'";
        } elseif (is_bool($value)) {
            $value = $value ? 1 : 0;
        } elseif ($value === null) {
            $value = 'NULL';
        }

        $this->lastCommand['modifiers']['default'] = "DEFAULT {$value}";

        return $this;
    }

    public function autoIncrement(): self
    {
        $this->ensureLastCommandIsColumn();
        $this->lastCommand['modifiers']['auto_increment'] = 'AUTO_INCREMENT';

        return $this;
    }

    public function after(string $column): self
    {
        if ($this->operation !== 'alter') {
            throw new RuntimeException('after() is typically only meaningful for Schema::table() operations or as part of a CHANGE statement.');
        }
        $this->ensureLastCommandIsColumn();
        $this->lastCommand['modifiers']['after'] = "AFTER `{$column}`";

        return $this;
    }

    public function onUpdateRaw(RawExpression $expression): self
    {
        $this->ensureLastCommandIsColumn();
        $this->lastCommand['modifiers']['on_update'] = 'ON UPDATE ' . $expression->getExpression();

        return $this;
    }

    protected function addIndexCommand(string|array $columns, string $type, ?string $name = null): self
    {
        $cols = is_array($columns) ? $columns : [$columns];

        if (is_null($name)) {
            $name = $this->table . '_' . implode('_', $cols) . '_' . strtolower($type);
        }

        $this->commands[] = [
            'type' => $this->operation === 'create' ? strtolower($type) : 'add_' . strtolower($type),
            'columns' => $cols,
            'key' => $name,
        ];

        return $this;
    }

    public function index(null|string|array $columns = null, ?string $name = null): self
    {
        $columns = $columns ?? $this->lastCommand['column'] ?? null;

        if (is_null($columns)) {
            throw new InvalidArgumentException('SchemaBuilder::index() requires a column name(s) when used as a separate command.');
        }

        return $this->addIndexCommand($columns, 'INDEX', $name);
    }

    public function unique(null|string|array $columns = null, ?string $name = null): self
    {
        $columns = $columns ?? $this->lastCommand['column'] ?? null;

        if (is_null($columns)) {
            throw new InvalidArgumentException('SchemaBuilder::unique() requires a column name(s) when used as a separate command.');
        }

        return $this->addIndexCommand($columns, 'UNIQUE_INDEX', $name);
    }

    public function fullText(null|string|array $columns = null, ?string $name = null): self
    {
        $columns = $columns ?? $this->lastCommand['column'] ?? null;

        if (is_null($columns)) {
            throw new InvalidArgumentException('SchemaBuilder::fullText() requires a column name(s) when used as a separate command.');
        }

        return $this->addIndexCommand($columns, 'FULLTEXT', $name);
    }

    public function foreign(string $column): self
    {
        $this->currentForeignKey = ['type' => $this->operation === 'create' ? 'constraint' : 'add_constraint', 'local_column' => $column, 'references_column' => null, 'on_table' => null, 'on_delete' => null, 'on_update' => null];

        return $this;
    }

    public function references(string $column): self
    {
        $this->currentForeignKey['references_column'] = $column;

        return $this;
    }

    public function on(string $table): self
    {
        $this->currentForeignKey['on_table'] = $table;

        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->currentForeignKey['on_delete'] = strtoupper($action);
        $this->finalizeForeignKey();

        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->currentForeignKey['on_update'] = strtoupper($action);
        $this->finalizeForeignKey();

        return $this;
    }

    protected function finalizeForeignKey(): void
    {
        if (empty($this->currentForeignKey) || ! ($this->currentForeignKey['on_table'] ?? null)) {
            return;
        }

        $localCol = $this->currentForeignKey['local_column'];
        $refCol = $this->currentForeignKey['references_column'] ?? 'id';
        $refTable = $this->currentForeignKey['on_table'];
        $keyName = "{$this->table}_{$localCol}_foreign";

        $definition = "CONSTRAINT `{$keyName}` FOREIGN KEY (`{$localCol}`) REFERENCES `{$refTable}` (`{$refCol}`)";

        if ($this->currentForeignKey['on_delete']) {
            $definition .= ' ON DELETE ' . $this->currentForeignKey['on_delete'];
        }

        if ($this->currentForeignKey['on_update']) {
            $definition .= ' ON UPDATE ' . $this->currentForeignKey['on_update'];
        }

        $this->commands[] = ['type' => $this->currentForeignKey['type'], 'definition' => $definition, 'key' => $keyName];
        $this->currentForeignKey = [];
        unset($this->lastCommand);
        $this->lastCommand = [];
    }

    public function primary(string|array $columns, string $name = 'PRIMARY'): self
    {
        unset($this->lastCommand);
        $this->lastCommand = [];

        $cols = is_array($columns) ? $columns : [$columns];
        $colString = '`' . implode('`, `', $cols) . '`';

        $this->commands[] = ['type' => $this->operation === 'create' ? 'constraint' : 'add_constraint', 'definition' => "PRIMARY KEY ({$colString})", 'key' => $name];
        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function uniqueKey(string|array $columns, string $name): self
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $colString = '`' . implode('`, `', $cols) . '`';

        $this->commands[] = ['type' => $this->operation === 'create' ? 'unique_key' : 'add_unique_key', 'definition' => "UNIQUE KEY `{$name}` ({$colString})", 'key' => $name];
        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function dropColumn(string|array $columns): self
    {
        if ($this->operation !== 'alter') {
            throw new RuntimeException('dropColumn can only be used with Schema::table().');
        }

        $cols = is_array($columns) ? $columns : [$columns];

        foreach ($cols as $column) {
            $this->commands[] = ['type' => 'drop', 'column' => $column];
        }

        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function renameColumn(string $from, string $to, string $typeDefinition): self
    {
        if ($this->operation !== 'alter') {
            throw new RuntimeException('renameColumn can only be used with Schema::table().');
        }

        $this->commands[] = ['type' => 'rename', 'from' => $from, 'to' => $to, 'definition' => $typeDefinition];
        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function dropForeign(string $name): self
    {
        if ($this->operation !== 'alter') {
            throw new RuntimeException('dropForeign can only be used with Schema::table().');
        }

        $keyName = str_contains($name, 'foreign') ? $name : "{$this->table}_{$name}_foreign";

        $this->commands[] = ['type' => 'drop_constraint', 'key' => $keyName];
        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function dropForeignByColumn(string $column): self
    {
        return $this->dropForeign($column);
    }

    public function dropIndex(string $name): self
    {
        if ($this->operation !== 'alter') {
            throw new RuntimeException('dropIndex can only be used with Schema::table().');
        }

        $keyName = str_contains($name, 'index') || str_contains($name, 'unique') ? $name : "{$this->table}_{$name}_index";

        $this->commands[] = ['type' => 'drop_index', 'key' => $keyName];
        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function dropIndexByColumns(string|array $columns): self
    {
        if ($this->operation !== 'alter') {
            throw new RuntimeException('dropIndexByColumns can only be used with Schema::table().');
        }

        $cols = is_array($columns) ? $columns : [$columns];
        $keyName = $this->table . '_' . implode('_', $cols) . '_index';

        $this->commands[] = ['type' => 'drop_index', 'key' => $keyName];
        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function dropUnique(string $name): self
    {
        if ($this->operation !== 'alter') {
            throw new RuntimeException('dropUnique can only be used with Schema::table().');
        }

        $keyName = str_contains($name, 'unique') ? $name : "{$this->table}_{$name}_unique";

        $this->commands[] = ['type' => 'drop_index', 'key' => $keyName];
        unset($this->lastCommand);
        $this->lastCommand = [];

        return $this;
    }

    public function dropUniqueByColumns(string|array $columns): self
    {
        if ($this->operation !== 'alter') {
            throw new RuntimeException('dropUniqueByColumns can only be used with Schema::table().');
        }

        $cols = is_array($columns) ? $columns : [$columns];
        $keyName = $this->table . '_' . implode('_', $cols) . '_unique';

        $this->commands[] = ['type' => 'drop_index', 'key' => $keyName];
        $this->lastCommand = [];

        return $this;
    }

    public function dropPrimary(): self
    {
        if ($this->operation !== 'alter') {
            throw new RuntimeException('dropPrimary can only be used with Schema::table().');
        }

        $this->commands[] = ['type' => 'drop_primary'];
        $this->lastCommand = [];

        return $this;
    }

    public function execute(): void
    {
        $this->finalizeForeignKey();

        if (empty($this->commands)) {
            return;
        }

        $grammar = $this->getGrammar($this->connection);
        $sql = '';

        if ($this->operation === 'create') {
            $options = [
                'engine' => $this->engine,
                'charset' => $this->charset,
                'collation' => $this->collation,
                'comment' => $this->tableComment,
            ];

            $sql = $grammar->compileCreate($this->table, $this->commands, $options);
        } elseif ($this->operation === 'alter') {
            $sql = $grammar->compileAlter($this->table, $this->commands);
        }

        if (empty($sql)) {
            return;
        }

        $statements = is_array($sql) ? $sql : [$sql];

        foreach ($statements as $statement) {
            if (! empty($statement)) {
                $this->connection->statement($statement);
            }
        }
    }

    public function whenDriverIs(string|array $drivers, callable $callback): self
    {
        $drivers = (array) $drivers;
        if (in_array($this->connection->getDriver(), $drivers)) {
            $callback($this);
        }

        return $this;
    }

    public function whenDriverIsNot(string|array $drivers, callable $callback): self
    {
        $drivers = (array) $drivers;
        if (! in_array($this->connection->getDriver(), $drivers)) {
            $callback($this);
        }

        return $this;
    }

    public function whenEnvironment(string|array $environments, callable $callback): self
    {
        $environments = (array) $environments;
        if (in_array(Environment::current(), $environments)) {
            $callback($this);
        }

        return $this;
    }

    public function whenNotEnvironment(string|array $environments, callable $callback): self
    {
        $environments = (array) $environments;
        if (! in_array(Environment::current(), $environments)) {
            $callback($this);
        }

        return $this;
    }

    public function foreignIfNotExist(string $column): self
    {
        $keyName = "{$this->table}_{$column}_foreign";

        if (! Schema::hasForeignKey($this->table, $keyName)) {
            return $this->foreign($column);
        }

        return $this;
    }

    public function dropForeignIfExists(string $name): self
    {
        $keyName = str_contains($name, 'foreign') ? $name : "{$this->table}_{$name}_foreign";

        if (Schema::hasForeignKey($this->table, $keyName)) {
            return $this->dropForeign($name);
        }

        return $this;
    }

    public function hasIndex(string $name): bool
    {
        $keyName = str_contains($name, 'index') ? $name : "{$this->table}_{$name}_index";

        return Schema::hasIndex($this->table, $keyName);
    }

    public function hasUnique(string $name): bool
    {
        $keyName = str_contains($name, 'unique') ? $name : "{$this->table}_{$name}_unique";

        return Schema::hasUnique($this->table, $keyName);
    }

    public function indexIfNotExist(string|array $columns, ?string $name = null): self
    {
        $cols = (array) $columns;
        $keyName = $name ?? $this->table . '_' . implode('_', $cols) . '_index';

        if (! $this->hasIndex($keyName)) {
            return $this->index($columns, $keyName);
        }

        return $this;
    }

    public function uniqueIfNotExist(string|array $columns, ?string $name = null): self
    {
        $cols = (array) $columns;
        $keyName = $name ?? $this->table . '_' . implode('_', $cols) . '_unique';

        if (! $this->hasUnique($keyName)) {
            return $this->unique($columns, $keyName);
        }

        return $this;
    }

    public function dropIndexIfExists(string $name): self
    {
        $keyName = str_contains($name, 'index') ? $name : "{$this->table}_{$name}_index";

        if (Schema::hasIndex($this->table, $keyName)) {
            return $this->dropIndex($name);
        }

        return $this;
    }

    public function dropUniqueIfExists(string $name): self
    {
        $keyName = str_contains($name, 'unique') ? $name : "{$this->table}_{$name}_unique";

        if (Schema::hasUnique($this->table, $keyName)) {
            return $this->dropUnique($name);
        }

        return $this;
    }

    public function hasColumn(string $column): bool
    {
        return Schema::hasColumn($this->table, $column);
    }

    public function dropColumnIfExists(string|array $columns): self
    {
        $cols = (array) $columns;
        foreach ($cols as $column) {
            if ($this->hasColumn($column)) {
                $this->dropColumn($column);
            }
        }

        return $this;
    }
}
