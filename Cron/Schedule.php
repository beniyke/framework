<?php

declare(strict_types=1);

namespace Cron;

use Cron\Interfaces\CronInterface;
use Cron\Interfaces\Schedulable;
use Helpers\DateTimeHelper;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Throwable;

class Schedule implements CronInterface
{
    /** @var Task[] */
    private array $tasks = [];

    private readonly PathResolverInterface $paths;

    public function __construct(PathResolverInterface $paths)
    {
        $this->paths = $paths;
    }

    public function command(string $signature): Task
    {
        $task = new Task($signature);
        $this->tasks[] = $task;

        return $task;
    }

    public function call(callable $callback): Task
    {
        $task = new Task();
        $task->call($callback);
        $this->tasks[] = $task;

        return $task;
    }

    public function task(): Task
    {
        $task = new Task();
        $this->tasks[] = $task;

        return $task;
    }

    public function discover(): void
    {
        $packagePath = $this->paths->basePath('packages');
        if (is_dir($packagePath)) {
            $packages = scandir($packagePath);
            foreach ($packages as $package) {
                if ($package === '.' || $package === '..') {
                    continue;
                }

                // Procedural: packages/{package}/schedule.php
                $scheduleFile = $packagePath . DIRECTORY_SEPARATOR . $package . DIRECTORY_SEPARATOR . 'schedule.php';
                if (file_exists($scheduleFile)) {
                    require_once $scheduleFile;
                }

                // Class-based: packages/{package}/Schedules/*Schedule.php
                $this->discoverClasses($packagePath . DIRECTORY_SEPARATOR . $package . DIRECTORY_SEPARATOR . 'Schedules', $package . '\\Schedules');
            }
        }

        // Discover in App Internal Modules (App/src/{Module}/Schedules)
        $appSourcePath = $this->paths->appSourcePath();
        if (is_dir($appSourcePath)) {
            $modules = scandir($appSourcePath);
            foreach ($modules as $module) {
                if ($module === '.' || $module === '..') {
                    continue;
                }

                // Class-based: App/src/{module}/Schedules/*Schedule.php
                $this->discoverClasses($appSourcePath . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'Schedules', $module . '\\Schedules');
            }
        }

        // Discover in App Core (App/Schedules)
        // Class-based only: App/Schedules/*Schedule.php
        $this->discoverClasses($this->paths->appPath('Schedules'), 'App\\Schedules');
    }

    /**
     * Discover and register schedulable classes in a directory.
     */
    private function discoverClasses(string $directory, string $namespace): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Enforce naming convention: *Schedule.php
            if (str_ends_with($file, 'Schedule.php')) {
                $className = $namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);

                if (class_exists($className)) {
                    try {
                        $instance = resolve($className);
                        if ($instance instanceof Schedulable) {
                            $instance->schedule($this);
                        }
                    } catch (Throwable $e) {
                        error_log("Failed to instantiate schedule class '{$className}': " . $e->getMessage());
                    }
                }
            }
        }
    }

    public function run(): void
    {
        $this->discover();
        $now = DateTimeHelper::now();

        foreach ($this->tasks as $task) {
            if ($task->isDue($now)) {
                $this->execute($task);
            }
        }
    }

    private function execute(Task $task): void
    {
        if ($callback = $task->getCallback()) {
            echo "Running scheduled callback..." . PHP_EOL;
            try {
                $callback();
                echo "Callback executed successfully." . PHP_EOL;
            } catch (Throwable $e) {
                error_log("Scheduled callback failed: " . $e->getMessage());
            }

            return;
        }

        $signature = $task->getSignature();
        if ($signature) {
            echo "Running scheduled command: {$signature}" . PHP_EOL;

            // Use dock() helper to run the command
            if (function_exists('dock')) {
                try {
                    $response = dock($signature)->run();
                    echo $response . PHP_EOL;
                } catch (Throwable $e) {
                    error_log("Scheduled command '{$signature}' failed: " . $e->getMessage());
                }
            }
        }
    }
}
