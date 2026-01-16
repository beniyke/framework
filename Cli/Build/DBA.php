<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * DBA (Database Administrator): Handles operational tasks like create/drop database,
 * listing tables, truncating tables, and import/export.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build;

use Cli\Helpers\ShellRunner;
use Database\ConnectionConfig;
use Database\ConnectionFactory;
use Database\ConnectionInterface;
use Database\Helpers\DatabaseOperationConfig;
use Database\NullConnection;
use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use RuntimeException;
use Throwable;

class DBA
{
    private PathResolverInterface $paths;

    private FileManipulationInterface $fileManipulator;

    private FileMetaInterface $fileMeta;

    private FileReadWriteInterface $fileReadWriter;

    private array $dbConfig;

    private DatabaseOperationConfig $operationConfig;

    private ConnectionInterface $connection;

    public function __construct(PathResolverInterface $paths, FileManipulationInterface $fileManipulator, FileMetaInterface $fileMeta, FileReadWriteInterface $fileReadWriter, array $dbConfig, DatabaseOperationConfig $operationConfig, ConnectionInterface $connection)
    {
        $this->paths = $paths;
        $this->fileManipulator = $fileManipulator;
        $this->fileMeta = $fileMeta;
        $this->fileReadWriter = $fileReadWriter;
        $this->dbConfig = $dbConfig;
        $this->operationConfig = $operationConfig;
        $this->connection = $connection;
    }

    private function resolveDefaultDatabaseName(): string
    {
        $fullConfig = $this->dbConfig;
        $driver = $fullConfig['driver'] ?? 'mysql';
        $dbConfig = $fullConfig['connections'][$driver] ?? [];

        if ($driver === 'sqlite') {
            $fullPath = trim($dbConfig['path'] ?? '', '/') . '/' . ($dbConfig['database'] ?? 'default.sqlite');

            return $fullPath;
        }

        return $dbConfig['name'] ?? 'default';
    }

    private function getBackupPath(): string
    {
        return $this->operationConfig->getBackupPath();
    }

    private function createServerConnection(): ConnectionInterface
    {
        $driver = $this->dbConfig['driver'] ?? 'mysql';
        $connectionConfig = $this->dbConfig['connections'][$driver] ?? $this->dbConfig;
        $config = $connectionConfig;

        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'])) {
            if (isset($config['name'])) {
                unset($config['name']);
            }

            if (isset($config['dsn'])) {
                $serverDsn = preg_replace('/;?dbname=[^;]*/', '', $config['dsn']);
                $config['dsn'] = trim($serverDsn, ';');
            }

            $configObject = ConnectionConfig::fromSingleConfig($config);

            return ConnectionFactory::create($configObject);
        }

        return $this->getApplicationConnection();
    }

    private function getApplicationConnection(): ConnectionInterface
    {
        try {
            $connection = $this->connection;

            if ($connection instanceof NullConnection) {
                throw new RuntimeException('DB Connection is a NullConnection. Cannot execute database commands.');
            }

            return $connection;
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to resolve application connection: ' . $e->getMessage(), 0, $e);
        }
    }

    public function createDatabase(?string $database_name = null): array
    {
        $dbName = $database_name ?: $this->resolveDefaultDatabaseName();
        $driver = $this->dbConfig['driver'] ?? 'sqlite';

        try {
            if ($driver === 'sqlite') {
                $connectionConfig = $this->dbConfig['connections']['sqlite'];
                $sqlitePath = trim($connectionConfig['path'], '/') . '/' . ($connectionConfig['database'] ?? $dbName);

                if ($this->fileMeta->exists($sqlitePath)) {
                    return ['status' => true, 'message' => "SQLite database file already exists at: {$sqlitePath}. Connection ready."];
                }

                $dirname = dirname($sqlitePath);

                if (! $this->fileMeta->isDir($dirname)) {
                    if (! $this->fileManipulator->mkdir($dirname)) {
                        return ['status' => false, 'message' => "Failed to create directory structure for SQLite database: {$dirname}"];
                    }
                }

                if ($this->fileReadWriter->put($sqlitePath, '')) {
                    return ['status' => true, 'message' => "SQLite database file successfully created at: {$sqlitePath}."];
                } else {
                    return ['status' => false, 'message' => "Failed to create the SQLite database file: {$sqlitePath}"];
                }
            } elseif (in_array($driver, ['mysql', 'mariadb', 'pgsql'])) {
                $connection = $this->createServerConnection();
                $connection->createDatabase($dbName);

                return ['status' => true, 'message' => "Database '{$dbName}' successfully created."];
            } else {
                return ['status' => false, 'message' => "Database creation not implemented for driver: {$driver}"];
            }
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'database exists') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                return ['status' => true, 'message' => "Database '{$dbName}' already exists. Connection ready."];
            }

            return ['status' => false, 'message' => 'Database creation failed: ' . $e->getMessage()];
        }
    }

    public function deleteDatabase(?string $database_name = null): array
    {
        $dbName = $database_name ?: $this->resolveDefaultDatabaseName();
        $driver = $this->dbConfig['driver'] ?? 'sqlite';

        try {
            if ($driver === 'sqlite') {
                $connectionConfig = $this->dbConfig['connections']['sqlite'];
                $sqlitePath = trim($connectionConfig['path'], '/') . '/' . ($connectionConfig['database'] ?? $dbName);

                if ($this->fileMeta->exists($sqlitePath)) {
                    if ($this->fileManipulator->delete($sqlitePath)) {
                        return ['status' => true, 'message' => "SQLite database file ({$sqlitePath}) successfully deleted."];
                    } else {
                        return ['status' => false, 'message' => "SQLite file deletion failed: Check file permissions for {$sqlitePath}."];
                    }
                } else {
                    return ['status' => true, 'message' => "SQLite database file ({$sqlitePath}) does not exist. No action needed."];
                }
            } elseif (in_array($driver, ['mysql', 'mariadb', 'pgsql'])) {
                $connection = $this->createServerConnection();
                $connection->dropDatabase($dbName);

                return ['status' => true, 'message' => "Database '{$dbName}' successfully deleted."];
            } else {
                return ['status' => false, 'message' => "Database deletion not implemented for driver: {$driver}"];
            }
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Database deletion failed: ' . $e->getMessage()];
        }
    }

    public function listDatabaseTables(array $exclude = []): array
    {
        try {
            $connection = $this->getApplicationConnection();
            $tables = $connection->getTables();
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
        }

        $tables = ! empty($exclude) ? array_diff($tables, $exclude) : $tables;

        if (count($tables) > 0) {
            $list = [];
            $index = 1;

            foreach (array_values($tables) as $table) {
                $list[] = ['#' => $index++, 'name' => (string) $table];
            }

            return [
                'status' => true,
                'message' => count($tables) . ' Tables found',
                'tables' => $list,
            ];
        }

        return ['status' => false, 'message' => 'No tables found in the database.'];
    }

    public function truncateDatabaseTable(?string $tablename = null): array
    {
        try {
            $connection = $this->getApplicationConnection();
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
        }

        $allTables = $connection->getTables();
        $tableList = $tablename !== null ? array_map('trim', explode(',', $tablename)) : $allTables;

        $tablesToTruncate = array_intersect($allTables, $tableList);

        if (empty($tablesToTruncate)) {
            $message = $tablename !== null
                ? 'No matching tables found or provided tables do not exist.'
                : 'No tables available to truncate.';

            return ['status' => false, 'message' => $message];
        }

        try {
            foreach ($tablesToTruncate as $table) {
                $connection->truncateTable($table);
            }

            return ['status' => true, 'message' => sprintf('Successfully truncated %d tables: %s.', count($tablesToTruncate), implode(', ', $tablesToTruncate))];
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Truncation failed for one or more tables: ' . $e->getMessage()];
        }
    }

    public function exportDatabase(?string $table_name = null): array
    {
        try {
            $connection = $this->getApplicationConnection();
            $driver = $connection->getDriver();
            $config = $connection->getConfig();

            $dbName = $config['database'] ?? $this->resolveDefaultDatabaseName();
            $ext = $driver === 'sqlite' ? '.sqlite' : '.sql';
            // For SQLite, use basename to avoid path issues in filename
            $baseDbName = $driver === 'sqlite' ? basename($dbName, '.sqlite') : $dbName;
            $filename = date('Ymd_His') . '_' . $baseDbName . $ext;
            $fullPath = rtrim($this->getBackupPath(), '/') . '/' . $filename;

            if (! $this->fileMeta->isDir($this->getBackupPath())) {
                if (! $this->fileManipulator->mkdir($this->getBackupPath())) {
                    return ['status' => false, 'message' => 'Failed to create backup directory: ' . $this->getBackupPath()];
                }
            }

            if (in_array($driver, ['mysql', 'mariadb'])) {
                $host = escapeshellarg($config['host'] ?? '127.0.0.1');
                $username = escapeshellarg($config['username'] ?? '');
                $database = escapeshellarg($dbName);
                $outputPath = escapeshellarg($fullPath);
                $tables = $table_name ? escapeshellarg($table_name) : '';

                $command = "mysqldump -h{$host} -u{$username} {$database} {$tables} > {$outputPath}";

                $env = [];
                if (isset($config['password']) && ! empty($config['password'])) {
                    $env['MYSQL_PWD'] = $config['password'];
                }

                $result = ShellRunner::execute($command, $env);

                if (isset($result['success']) && $result['success'] === false) {
                    return ['status' => false, 'message' => "MySQL export failed. Check 'mysqldump' and database credentials. Error: " . ($result['error'] ?? 'Unknown shell error')];
                }

                return [
                    'status' => true,
                    'message' => "Database exported to MySQL file: {$fullPath}.",
                    'filepath' => $fullPath,
                ];
            } elseif ($driver === 'pgsql') {
                $host = escapeshellarg($config['host'] ?? '127.0.0.1');
                $port = escapeshellarg($config['port'] ?? '5432');
                $username = escapeshellarg($config['username'] ?? '');
                $database = escapeshellarg($dbName);
                $outputPath = escapeshellarg($fullPath);

                $command = "pg_dump -h {$host} -p {$port} -U {$username} -d {$database} > {$outputPath}";

                if ($table_name) {
                    $table_args = array_map(fn ($t) => '-t ' . escapeshellarg(trim($t)), explode(',', $table_name));
                    $command = "pg_dump -h {$host} -p {$port} -U {$username} -d {$database} " . implode(' ', $table_args) . " > {$outputPath}";
                }

                $env = [];

                if (isset($config['password']) && ! empty($config['password'])) {
                    $env['PGPASSWORD'] = $config['password'];
                }

                $result = ShellRunner::execute($command, $env);

                if (isset($result['success']) && $result['success'] === false) {
                    return ['status' => false, 'message' => "PostgreSQL export failed. Check 'pg_dump' and database credentials. Error: " . ($result['error'] ?? 'Unknown shell error')];
                }

                return [
                    'status' => true,
                    'message' => "Database exported to PostgreSQL file: {$fullPath}.",
                    'filepath' => $fullPath,
                ];
            } elseif ($driver === 'sqlite') {
                $dbFile = $config['database'] ?? $this->resolveDefaultDatabaseName();

                if (! $this->fileManipulator->copy($dbFile, $fullPath)) {
                    throw new RuntimeException("Failed to copy SQLite database file from {$dbFile} to {$fullPath}.");
                }

                return [
                    'status' => true,
                    'message' => "SQLite database file copied to: {$fullPath}.",
                    'filepath' => $fullPath,
                ];
            }

            return ['status' => false, 'message' => "Export not implemented for driver: {$driver}"];
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Database export failed: ' . $e->getMessage()];
        }
    }

    public function importDatabase(string $filename, ?string $path = null): array
    {
        $backupPath = $path ? $this->paths->basePath($path) : $this->getBackupPath();
        $fullPath = rtrim($backupPath, '/') . '/' . $filename;

        if (! $this->fileMeta->exists($fullPath)) {
            return ['status' => false, 'message' => "Import file not found: {$fullPath}"];
        }

        try {
            $connection = $this->getApplicationConnection();
            $driver = $connection->getDriver();
            $config = $connection->getConfig();

            if (in_array($driver, ['mysql', 'mariadb'])) {
                $host = escapeshellarg($config['host'] ?? '127.0.0.1');
                $username = escapeshellarg($config['username'] ?? '');
                $database = escapeshellarg($config['database'] ?? $this->resolveDefaultDatabaseName());
                $inputPath = escapeshellarg($fullPath);
                $command = "mysql -h{$host} -u{$username} {$database} < {$inputPath}";

                $env = [];
                if (isset($config['password']) && ! empty($config['password'])) {
                    $env['MYSQL_PWD'] = $config['password'];
                }

                $result = ShellRunner::execute($command, $env);

                if (isset($result['success']) && $result['success'] === false) {
                    return ['status' => false, 'message' => "MySQL import failed. Check 'mysql' command and database credentials. Error: " . ($result['error'] ?? 'Unknown shell error')];
                }

                return [
                    'status' => true,
                    'message' => "MySQL database imported from file: {$filename}.",
                ];
            } elseif ($driver === 'pgsql') {
                $host = escapeshellarg($config['host'] ?? '127.0.0.1');
                $port = escapeshellarg($config['port'] ?? '5432');
                $username = escapeshellarg($config['username'] ?? '');
                $database = escapeshellarg($config['database'] ?? $this->resolveDefaultDatabaseName());
                $inputPath = escapeshellarg($fullPath);

                $command = "psql -h {$host} -p {$port} -U {$username} -d {$database} -f {$inputPath}";

                $env = [];
                if (isset($config['password']) && ! empty($config['password'])) {
                    $env['PGPASSWORD'] = $config['password'];
                }

                $result = ShellRunner::execute($command, $env);

                if (isset($result['success']) && $result['success'] === false) {
                    return ['status' => false, 'message' => "PostgreSQL import failed. Check 'psql' command and database credentials. Error: " . ($result['error'] ?? 'Unknown shell error')];
                }

                return [
                    'status' => true,
                    'message' => "PostgreSQL database imported from file: {$filename}.",
                ];
            } elseif ($driver === 'sqlite') {
                $dbFile = $config['database'] ?? $this->resolveDefaultDatabaseName();

                if (! $this->fileManipulator->copy($fullPath, $dbFile)) {
                    throw new RuntimeException("Failed to restore SQLite database from {$fullPath} to {$dbFile}.");
                }

                return [
                    'status' => true,
                    'message' => "SQLite database restored from file: {$filename}.",
                ];
            }

            return ['status' => false, 'message' => "Import not implemented for driver: {$driver}"];
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Database import failed: ' . $e->getMessage()];
        }
    }
}
