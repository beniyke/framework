<?php

declare(strict_types=1);

namespace Database;

/**
 * Anchor Framework
 *
 * ConnectionConfig is a value object representing the configuration
 * for a specific database connection. It provides methods to retrieve driver parameters, DSN, and options.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use RuntimeException;

class ConnectionConfig implements ConnectionConfigInterface
{
    private array $dbConfig;

    private string $activeDriver;

    public static function fromFullConfig(array $fullConfig): self
    {
        $driver = ($fullConfig['driver'] ?? 'sqlite');
        $dbConfig = $fullConfig['connections'][$driver] ?? null;

        if (is_null($dbConfig)) {
            throw new RuntimeException("Database connection configuration not found for driver: {$driver}");
        }

        $dbConfig['driver'] = $driver;

        return new self($dbConfig, $driver);
    }

    public static function fromSingleConfig(array $singleConfig): self
    {
        $driver = ($singleConfig['driver'] ?? 'mysql');

        return new self($singleConfig, $driver);
    }

    public static function getTestConfig(): array
    {
        return [
            'driver' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'path' => null,
                    'database' => 'memory',
                    'busy_timeout' => null,
                    'journal_mode' => 'WAL',
                    'synchronous' => null,
                    'persistent' => true,
                    'options' => [],
                ],
            ],
        ];
    }

    public static function fromTestConfig(): self
    {
        $config = static::getTestConfig();

        return static::fromFullConfig($config);
    }

    private function __construct(array $dbConfig, string $driver)
    {
        $this->dbConfig = $dbConfig;
        $this->activeDriver = $driver;
    }

    public function getDsn(): string
    {
        $driver = $this->getDriver();
        $dbConfig = $this->dbConfig;

        if ($driver === 'sqlite') {
            $database = $dbConfig['database'];
            if (! empty($dbConfig['path'])) {
                $database = trim($dbConfig['path'] ?? '', '/') . '/' . ($database ?? 'default.sqlite');
            }

            return "{$driver}:{$database}";
        }

        $dsn = "{$driver}:";

        if (isset($dbConfig['unix_socket']) && ! empty($dbConfig['unix_socket'])) {
            $dsn .= "unix_socket={$dbConfig['unix_socket']}";
        } else {
            $host = $dbConfig['host'] ?? '127.0.0.1';
            $dsn .= "host={$host}";

            if (isset($dbConfig['port']) && ! empty($dbConfig['port'])) {
                $dsn .= ";port={$dbConfig['port']}";
            }
        }

        if (isset($dbConfig['name']) && ! empty($dbConfig['name'])) {
            $dsn .= ";dbname={$dbConfig['name']}";
        }

        if (isset($dbConfig['charset']) && ! empty($dbConfig['charset'])) {
            $dsn .= ";charset={$dbConfig['charset']}";
        }

        return $dsn;
    }

    public function getDriver(): string
    {
        return $this->activeDriver;
    }

    public function getUser(): string
    {
        return $this->dbConfig['user'] ?? '';
    }

    public function getPassword(): string
    {
        return $this->dbConfig['password'] ?? '';
    }

    public function getTimezone(): string
    {
        return $this->dbConfig['timezone'] ?? 'UTC';
    }

    public function getOptions(): array
    {
        return $this->dbConfig['options'] ?? [];
    }

    public function isPersistent(): bool
    {
        return (bool) ($this->dbConfig['persistent'] ?? false);
    }

    public function getConfigArray(): array
    {
        return $this->dbConfig;
    }
}
