<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Manages the execution of database seeders.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Migration;

use Database\ConnectionInterface;
use RuntimeException;
use Throwable;

class SeedManager
{
    protected ConnectionInterface $connection;

    protected string $seederPath;

    public function __construct(ConnectionInterface $connection, string $seederPath)
    {
        $this->connection = $connection;
        $this->seederPath = rtrim($seederPath, '/');
    }

    public function run(string $className = 'DatabaseSeeder'): array
    {
        $startTime = microtime(true);

        try {
            $seeder = $this->resolve($className);
            $seeder->setConnection($this->connection);
            $seeder->setManager($this);
            $seeder->run();

            $timeTaken = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'time' => $timeTaken,
                'class' => $className,
            ];
        } catch (Throwable $e) {
            throw new RuntimeException("Seeding FAILED for class {$className}. Error: {$e->getMessage()}", 0, $e);
        }
    }

    protected function resolve(string $className): BaseSeeder
    {
        if (class_exists($className)) {
            $instance = new $className();
        } else {
            $normalizedClassName = str_replace('\\', '/', $className);
            $filePath = "{$this->seederPath}/{$normalizedClassName}.php";

            if (! file_exists($filePath)) {
                throw new RuntimeException("Seeder file {$filePath} not found.");
            }

            require_once $filePath;

            if (! class_exists($className)) {
                throw new RuntimeException("Seeder class {$className} not defined in {$filePath}.");
            }

            $instance = new $className();
        }

        if (! $instance instanceof BaseSeeder) {
            throw new RuntimeException("Class {$className} must extend Database\\Migration\\BaseSeeder.");
        }

        return $instance;
    }
}
