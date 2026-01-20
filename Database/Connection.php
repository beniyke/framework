<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Connection manages the raw database connection via PDO.
 * It handles query execution, transaction management, and schema operations
 * for supported database drivers (MySQL, SQLite, PostgreSQL).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database;

use Database\Query\Builder;
use Database\Query\Grammar;
use Database\Query\MySqlGrammar;
use Database\Query\PostgresGrammar;
use Database\Query\SqliteGrammar;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

class Connection implements ConnectionInterface
{
    private PDO $pdo;

    public static array $queryLog = [];

    public static array $queryCallbacks = [];

    private string $name = 'default';

    private string $driver;

    private array $config = [];

    private array $pdoOptions = [];

    private bool $isPersistent = false;

    private ?float $lastQueryStart = null;

    private array $commitCallbacks = [];

    private array $rollbackCallbacks = [];

    private array $statementCache = [];

    private int $maxCacheSize = 100;

    private int $cacheHits = 0;

    private int $cacheMisses = 0;

    private int $transactionLevel = 0;

    private function __construct(string $dsn, ?string $username = null, ?string $password = null)
    {
        $this->config = [
            'dsn' => $dsn,
            'username' => $username,
            'password' => $password,
        ];
        $this->extractDbNameAndHost();
    }

    private function extractDbNameAndHost(): void
    {
        $parts = explode(':', $this->config['dsn'], 2);
        $driver = $parts[0] ?? '';
        $dsnBody = $parts[1] ?? '';
        if (in_array($driver, ['mysql', 'mariadb'])) {
            $params = [];
            foreach (explode(';', $dsnBody) as $param) {
                if (str_contains($param, '=')) {
                    [$key, $value] = explode('=', $param, 2);
                    $params[trim($key)] = trim($value);
                }
            }
            $this->config['database'] = $params['dbname'] ?? null;
            $this->config['host'] = $params['host'] ?? null;
        } elseif ($driver === 'sqlite') {
            $this->config['database'] = ltrim($dsnBody, '/');
            $this->config['host'] = 'localhost';
        } elseif ($driver === 'pgsql') {
            $params = [];
            foreach (explode(' ', $dsnBody) as $param) {
                if (str_contains($param, '=')) {
                    [$key, $value] = explode('=', $param, 2);
                    $params[trim($key)] = trim($value);
                }
            }
            $this->config['database'] = $params['dbname'] ?? ($params['database'] ?? null);
            $this->config['host'] = $params['host'] ?? null;
        }
    }

    public static function configure(string $dsn, ?string $username = null, ?string $password = null): self
    {
        return new static($dsn, $username, $password);
    }

    public function connect(): self
    {
        $this->initializePdo();

        return $this;
    }

    public function disconnect(): void
    {
        $this->statementCache = [];
        unset($this->pdo);
    }

    public function options(array $options): self
    {
        $this->pdoOptions = array_replace($this->pdoOptions, $options);

        return $this;
    }

    public function persistent(bool $isPersistent = true): self
    {
        $this->isPersistent = $isPersistent;

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    private function initializePdo(): void
    {
        $this->clearStatementCache();
        try {
            $options = array_replace($this->pdoOptions, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            if ($this->isPersistent) {
                $options[PDO::ATTR_PERSISTENT] = true;
            }
            $this->pdo = new PDO(
                $this->config['dsn'],
                $this->config['username'],
                $this->config['password'],
                $options
            );

            $this->driver = explode(':', $this->config['dsn'])[0];

            if ($this->driver === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = ON;');
                $this->pdo->sqliteCreateFunction('regexp', fn ($pattern, $value) => preg_match($pattern, $value) ? 1 : 0);
            }
        } catch (PDOException $e) {
            throw new RuntimeException("DB Connection '{$this->name}' failed: " . $e->getMessage());
        }
    }

    public function afterCommit(callable $callback): void
    {
        $this->commitCallbacks[] = $callback;
    }

    public function afterRollback(callable $callback): void
    {
        $this->rollbackCallbacks[] = $callback;
    }

    public function initCommand(string $command): self
    {
        $this->ensureConnected();
        $this->pdo->exec($command);

        return $this;
    }

    public function getPdo(): PDO
    {
        $this->ensureConnected();

        return $this->pdo;
    }

    public function getDriver(): string
    {
        $this->ensureConnected();

        return $this->driver;
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

    public function getConfig(): array
    {
        return $this->config;
    }

    public function newGrammar(): Grammar
    {
        $this->ensureConnected();

        return match ($this->driver) {
            'mysql', 'mariadb' => new MySqlGrammar($this->driver),
            'sqlite' => new SqliteGrammar($this->driver),
            'pgsql' => new PostgresGrammar($this->driver),
            default => throw new RuntimeException("Unsupported driver: {$this->driver}. Implement Grammar."),
        };
    }

    public function table(string $table): Builder
    {
        return (new Builder($this, $this->newGrammar()))->from($table);
    }

    private function ensureConnected(): void
    {
        if (! isset($this->pdo)) {
            $this->initializePdo();
        }
    }

    private function prepareStatement(string $sql): PDOStatement
    {
        if (isset($this->statementCache[$sql])) {
            $this->cacheHits++;

            return $this->statementCache[$sql];
        }
        $this->cacheMisses++;
        if (count($this->statementCache) >= $this->maxCacheSize) {
            array_shift($this->statementCache);
        }

        $stmt = $this->pdo->prepare($sql);
        $this->statementCache[$sql] = $stmt;

        return $stmt;
    }

    public function execute(string $sql, array $bindings = []): PDOStatement
    {
        $this->ensureConnected();
        $stmt = $this->prepareStatement($sql);
        $this->lastQueryStart = microtime(true);
        $stmt->execute($bindings);
        $this->logQuery($sql, $bindings);

        return $stmt;
    }

    private function logQuery(string $sql, array $bindings): void
    {
        $timeMs = round((microtime(true) - $this->lastQueryStart) * 1000, 2);

        $queryData = [
            'connection' => $this->name,
            'sql' => $sql,
            'bindings' => $bindings,
            'time_ms' => $timeMs,
        ];

        self::$queryLog[] = $queryData;

        // Call registered callbacks for Watcher integration
        foreach (self::$queryCallbacks as $callback) {
            $callback($queryData);
        }
    }

    public static function listen(callable $callback): void
    {
        self::$queryCallbacks[] = $callback;
    }

    public static function clearQueryLog(): void
    {
        self::$queryLog = [];
    }

    public static function getQueryLog(): array
    {
        return self::$queryLog;
    }

    public function getCacheStats(): array
    {
        $total = $this->cacheHits + $this->cacheMisses;
        $hitRate = $total > 0 ? round(($this->cacheHits / $total) * 100, 2) : 0;

        return [
            'hits' => $this->cacheHits,
            'misses' => $this->cacheMisses,
            'size' => count($this->statementCache),
            'max_size' => $this->maxCacheSize,
            'hit_rate' => $hitRate . '%',
        ];
    }

    public function setMaxCacheSize(int $size): self
    {
        $this->maxCacheSize = max(1, $size);
        while (count($this->statementCache) > $this->maxCacheSize) {
            array_shift($this->statementCache);
        }

        return $this;
    }

    public function clearStatementCache(): void
    {
        $this->statementCache = [];
        $this->cacheHits = 0;
        $this->cacheMisses = 0;
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        $this->ensureConnected();
        $this->execute($sql, $bindings);

        return true;
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->execute($sql, $bindings)->fetchAll();
    }

    public function selectOne(string $sql, array $bindings = []): array|false
    {
        $stmt = $this->execute($sql, $bindings);
        $result = $stmt->fetch();
        $stmt->closeCursor();

        return $result;
    }

    public function insertGetId(string $sql, array $bindings = []): string
    {
        $this->execute($sql, $bindings);

        return $this->pdo->lastInsertId();
    }

    public function update(string $sql, array $bindings = []): int
    {
        return $this->execute($sql, $bindings)->rowCount();
    }

    public function delete(string $sql, array $bindings = []): int
    {
        return $this->execute($sql, $bindings)->rowCount();
    }

    public function beginTransaction(): bool
    {
        $this->ensureConnected();

        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT transp{$this->transactionLevel}");
        }

        $this->transactionLevel++;

        return true;
    }

    public function inTransaction(): bool
    {
        $this->ensureConnected();

        return $this->transactionLevel > 0;
    }

    public function commit(): void
    {
        $this->ensureConnected();

        if ($this->transactionLevel <= 0) {
            return;
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            $this->pdo->commit();
            foreach ($this->commitCallbacks as $cb) {
                $cb();
            }
            $this->commitCallbacks = [];
        } else {
            $this->pdo->exec("RELEASE SAVEPOINT transp{$this->transactionLevel}");
        }
    }

    public function rollBack(): void
    {
        $this->ensureConnected();

        if ($this->transactionLevel <= 0) {
            return;
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            $this->pdo->rollBack();
            foreach ($this->rollbackCallbacks as $cb) {
                $cb();
            }
            $this->rollbackCallbacks = [];
        } else {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT transp{$this->transactionLevel}");
        }
    }

    public function transaction(callable $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    public function getTables(): array
    {
        $this->ensureConnected();

        $sql = match ($this->driver) {
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'",
            'mysql', 'mariadb' => "
                SELECT table_name AS table_name
                FROM information_schema.tables
                WHERE table_type = 'BASE TABLE'
                AND table_schema = " . ($this->config['database'] ? $this->pdo->quote($this->config['database']) : 'DATABASE()'),
            'pgsql' => "
                SELECT table_name
                FROM information_schema.tables
                WHERE table_type = 'BASE TABLE'
                AND table_schema = 'public'
                ORDER BY table_name
            ",
            default => throw new RuntimeException("Database driver {$this->driver} not supported for introspection."),
        };

        $results = $this->select($sql);

        if (empty($results)) {
            return [];
        }

        // Get the first column of each row, regardless of its key case
        return array_map(fn ($row) => (string) reset($row), $results);
    }

    public function tableExists(string $table): bool
    {
        return in_array($table, $this->getTables());
    }

    public function columnExists(string $table, string $column): bool
    {
        $this->ensureConnected();

        $sql = match ($this->driver) {
            'sqlite' => "PRAGMA table_info({$table})",
            'mysql', 'mariadb' => '
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                AND table_name = ?
                AND column_name = ?
            ',
            'pgsql' => "
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = 'public'
                AND table_name = ?
                AND column_name = ?
            ",
            default => throw new RuntimeException("Database driver {$this->driver} not supported for column introspection."),
        };

        if ($this->driver === 'sqlite') {
            $results = $this->select($sql);
            foreach ($results as $row) {
                if ($row['name'] === $column) {
                    return true;
                }
            }

            return false;
        }

        $bindings = [$table, $column];

        return (bool) $this->selectOne($sql, $bindings);
    }

    public function createDatabase(string $databaseName): void
    {
        $this->ensureConnected();

        $sql = match ($this->driver) {
            'mysql', 'mariadb' => "CREATE DATABASE IF NOT EXISTS `{$databaseName}`",
            'pgsql' => "CREATE DATABASE \"{$databaseName}\"",
            default => throw new RuntimeException("Database creation not supported for driver: {$this->driver}"),
        };

        $this->pdo->exec($sql);
    }

    public function dropDatabase(string $databaseName): void
    {
        $this->ensureConnected();

        $sql = match ($this->driver) {
            'mysql', 'mariadb' => "DROP DATABASE IF EXISTS `{$databaseName}`",
            'pgsql' => "DROP DATABASE IF EXISTS \"{$databaseName}\"",
            default => throw new RuntimeException("Database deletion not supported for driver: {$this->driver}"),
        };

        $this->pdo->exec($sql);
    }

    public function truncateTable(string $table): void
    {
        $this->execute("DELETE FROM {$table}");
    }

    public function dropAllTables(): void
    {
        $this->ensureConnected();
        $tables = $this->getTables();

        if (empty($tables)) {
            return;
        }

        if ($this->driver === 'sqlite') {
            $this->statement('PRAGMA foreign_keys = OFF');
            foreach ($tables as $table) {
                $this->statement("DROP TABLE IF EXISTS `{$table}`");
            }
            $this->statement('PRAGMA foreign_keys = ON');
        } elseif ($this->driver === 'mysql' || $this->driver === 'mariadb') {
            $this->statement('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $table) {
                $this->statement("DROP TABLE IF EXISTS `{$table}`");
            }
            $this->statement('SET FOREIGN_KEY_CHECKS = 1');
        } elseif ($this->driver === 'pgsql') {
            foreach ($tables as $table) {
                $this->statement("DROP TABLE IF EXISTS \"{$table}\" CASCADE");
            }
        }
    }
}
