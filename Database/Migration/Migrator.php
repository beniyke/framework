<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Migrator manages the execution and rollback of database migrations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Migration;

use Database\ConnectionInterface;
use Database\Schema\Schema;
use RuntimeException;
use Throwable;

class Migrator
{
    protected MigrationRepository $repository;

    protected ConnectionInterface $connection;

    protected array $paths = [];

    protected array $migrationMap = [];

    protected array $sortedMigrationNames = [];

    protected bool $useTransactions = true;

    public function __construct(ConnectionInterface $connection, string|array $migrationPath)
    {
        $this->connection = $connection;
        $this->repository = new MigrationRepository($connection);
        $this->paths = (array) $migrationPath;
        Schema::setConnection($connection);
        $this->sortedMigrationNames = $this->getMigrationFiles();
    }

    public function setUseTransactions(bool $use): self
    {
        $this->useTransactions = $use;

        return $this;
    }

    public function run(): array
    {
        $files = $this->sortedMigrationNames;
        $ran = $this->repository->getRan();
        $migrations = array_diff($files, $ran);
        $batch = $this->repository->getNextBatchNumber();
        $executed = [];

        if (empty($migrations)) {
            return [];
        }

        foreach ($migrations as $file) {
            $executed[] = $this->runUp($file, $batch);
        }

        return $executed;
    }

    public function getPendingMigrations(): array
    {
        $files = $this->sortedMigrationNames;
        $ran = $this->repository->getRan();

        return array_diff($files, $ran);
    }

    public function runFiles(array $fileNames): array
    {
        $ran = $this->repository->getRan();
        $batch = $this->repository->getNextBatchNumber();
        $executed = [];

        foreach ($fileNames as $fileName) {
            if (in_array($fileName, $ran)) {
                continue;
            }

            if (!in_array($fileName, $this->sortedMigrationNames)) {
                continue;
            }

            $executed[] = $this->runUp($fileName, $batch);
        }

        return $executed;
    }

    public function rollbackFile(string $file): array
    {
        return $this->runDown($file);
    }

    public function rollback(): array
    {
        $lastBatch = $this->repository->getLastBatch();

        return $this->rollbackMigrations($lastBatch);
    }

    public function rollbackSteps(int $steps = 1): array
    {
        $migrationsToRollback = $this->repository->getMigrationsForSteps($steps);

        return $this->rollbackMigrations($migrationsToRollback);
    }

    public function reset(): array
    {
        $allMigrated = $this->repository->getMigratedFiles();

        if (empty($allMigrated)) {
            return [];
        }

        $rolledBack = $this->rollbackMigrations($allMigrated);

        $this->repository->dropTable();
        $this->repository = new MigrationRepository($this->connection);

        return $rolledBack;
    }

    public function refresh(): array
    {
        $rollbackResults = $this->reset();
        $migrationResults = $this->run();

        return ['rolledBack' => $rollbackResults, 'migrated' => $migrationResults];
    }

    protected function runUp(string $file, int $batch): array
    {
        $instance = $this->resolve($file);
        $startTime = microtime(true);

        try {
            $instance->up();

            $runLog = function () use ($file, $batch) {
                $this->repository->log($file, $batch);
            };

            if ($this->useTransactions) {
                $this->connection->transaction($runLog);
            } else {
                $runLog();
            }

            $timeTaken = round(microtime(true) - $startTime, 2);

            return ['file' => $file, 'time' => $timeTaken];
        } catch (Throwable $e) {
            throw new RuntimeException("Migration FAILED: {$file}. Error: {$e->getMessage()}", 0, $e);
        }
    }

    protected function runDown(string $file): array
    {
        $instance = $this->resolve($file);
        $startTime = microtime(true);

        try {
            $instance->down();

            $runLog = function () use ($file) {
                $this->repository->delete($file);
            };

            if ($this->useTransactions) {
                $this->connection->transaction($runLog);
            } else {
                $runLog();
            }

            $timeTaken = round(microtime(true) - $startTime, 2);

            return ['file' => $file, 'time' => $timeTaken];
        } catch (Throwable $e) {
            throw new RuntimeException("Rollback FAILED: {$file}. Error: {$e->getMessage()}", 0, $e);
        }
    }

    protected function rollbackMigrations(array $migrations): array
    {
        if (empty($migrations)) {
            return [];
        }

        $rolledBack = [];

        foreach ($migrations as $migration) {
            $file = $migration['migration'];
            if (! isset($this->migrationMap[$file])) {
                continue;
            }
            $rolledBack[] = $this->runDown($file);
        }

        return $rolledBack;
    }

    protected function getMigrationFiles(): array
    {
        $names = [];
        $this->migrationMap = [];

        foreach ($this->paths as $path) {
            $path = rtrim($path, '/');
            if (! is_dir($path)) {
                continue;
            }

            $files = glob("{$path}/*.php");
            foreach ($files as $file) {
                $name = basename($file, '.php');
                $names[] = $name;
                $this->migrationMap[$name] = $file;
            }
        }

        sort($names);

        return $names;
    }

    protected function resolve(string $file): BaseMigration
    {
        if (! isset($this->migrationMap[$file])) {
            throw new RuntimeException("Migration file {$file} not found.");
        }

        $filePath = $this->migrationMap[$file];

        // Calculate the expected class name from the filename
        $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $file);
        $className = str_replace(['_', '-'], ' ', $className);
        $className = ucwords($className);
        $className = str_replace(' ', '', $className);

        // Determine the full class name (including namespace) by inspecting the file content
        $namespace = $this->getNamespace($filePath);
        $fullClassName = $namespace ? "{$namespace}\\{$className}" : $className;

        // Only require the file if the class is not already defined.
        // This prevents fatal errors if the migration file is duplicated (e.g. in both System and App).
        if (! class_exists($fullClassName)) {
            require_once $filePath;
        }

        if (class_exists($fullClassName)) {
            $instance = new $fullClassName();
        } else {
            throw new RuntimeException("Migration class {$fullClassName} could not be resolved from file {$filePath}.");
        }

        if (! $instance instanceof BaseMigration) {
            throw new RuntimeException("Migration class {$fullClassName} must extend Database\Migration\BaseMigration.");
        }

        return $instance;
    }

    protected function getNamespace(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if (preg_match('/namespace\s+([^;]+);/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    public function getStatus(): array
    {
        $files = $this->sortedMigrationNames;
        $ran = $this->repository->getRan();
        $status = [];

        foreach ($files as $file) {
            $status[$file] = in_array($file, $ran) ? 'RAN' : 'PENDING';
        }

        return $status;
    }

    public function getRepository(): MigrationRepository
    {
        return $this->repository;
    }
}
