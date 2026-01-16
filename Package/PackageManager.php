<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * PackageManager manages the installation, uninstallation, and configuration of packages.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Package;

use Database\ConnectionInterface;
use Database\Helpers\DatabaseOperationConfig;
use Database\Migration\Migrator;
use FilesystemIterator;
use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use RuntimeException;
use Throwable;

class PackageManager
{
    public const STATUS_INSTALLED = 'INSTALLED';
    public const STATUS_MISSING_FILES = 'MISSING_FILES';
    public const STATUS_NOT_INSTALLED = 'NOT_INSTALLED';

    private PathResolverInterface $paths;

    private FileMetaInterface $fileMeta;

    private FileReadWriteInterface $fileReadWrite;

    private FileManipulationInterface $fileManipulation;

    private ?Migrator $migrator = null;

    public function __construct(PathResolverInterface $paths, FileMetaInterface $fileMeta, FileReadWriteInterface $fileReadWrite, FileManipulationInterface $fileManipulation)
    {
        $this->paths = $paths;
        $this->fileMeta = $fileMeta;
        $this->fileReadWrite = $fileReadWrite;
        $this->fileManipulation = $fileManipulation;
    }

    public function resolvePackagePath(string $package, bool $isSystem): string
    {
        $base = $isSystem
            ? $this->paths->systemPath($package)
            : $this->paths->basePath("packages/{$package}");

        if (!$this->fileMeta->isDir($base)) {
            throw new RuntimeException("Package not found at: {$base}");
        }

        return $base;
    }

    public function getManifest(string $packagePath): array
    {
        $setupFile = $packagePath . DIRECTORY_SEPARATOR . 'setup.php';
        if ($this->fileMeta->isFile($setupFile)) {
            $manifest = require $setupFile;
            if (is_array($manifest)) {
                return $manifest;
            }
        }

        return [];
    }

    public function publishConfig(string $packagePath): int
    {
        $source = $packagePath . DIRECTORY_SEPARATOR . 'Config';
        if (!$this->fileMeta->isDir($source)) {
            return 0;
        }

        $dest = $this->paths->appPath('Config');

        return $this->copyDirectoryContents($source, $dest);
    }

    public function publishMigrations(string $packagePath): int
    {
        $source = $packagePath . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';
        if (!$this->fileMeta->isDir($source)) {
            return 0;
        }

        $dest = $this->paths->storagePath('database/migrations');

        if (!$this->fileMeta->isDir($dest)) {
            $this->fileManipulation->mkdir($dest, 0755, true);
        }

        return $this->copyDirectoryContents($source, $dest);
    }

    public function install(string $packagePath, array $manifest = []): array
    {
        $results = [
            'config_count' => 0,
            'migration_count' => 0,
            'migrations_run' => 0,
            'migration_files' => [],
            'errors' => [],
        ];

        // Publish Config
        try {
            $results['config_count'] = $this->publishConfig($packagePath);
        } catch (Throwable $e) {
            $results['errors'][] = "Config publishing failed: " . $e->getMessage();
        }

        // Publish Migrations and track which files were published
        try {
            $migrationSource = $packagePath . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';

            if ($this->fileMeta->isDir($migrationSource)) {
                $files = scandir($migrationSource);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $results['migration_files'][] = pathinfo($file, PATHINFO_FILENAME);
                }
            }

            $results['migration_count'] = $this->publishMigrations($packagePath);
        } catch (Throwable $e) {
            $results['errors'][] = "Migration publishing failed: " . $e->getMessage();
        }

        // Run Migrations Automatically
        if ($results['migration_count'] > 0) {
            try {
                $connection = resolve(ConnectionInterface::class);
                $operationConfig = resolve(DatabaseOperationConfig::class);
                $migrator = new Migrator($connection, $operationConfig->getMigrationsPath());

                $pending = $migrator->getPendingMigrations();
                if (!empty($pending)) {
                    $migrator->run();
                    $results['migrations_run'] = count($pending);
                }
            } catch (Throwable $e) {
                $results['errors'][] = "Migration execution failed: " . $e->getMessage();
            }
        }

        // Register Services
        if (!empty($manifest)) {
            try {
                if (isset($manifest['providers'])) {
                    $this->registerProviders($manifest['providers']);
                }
                if (isset($manifest['middleware'])) {
                    $this->registerMiddleware($manifest['middleware']);
                }
            } catch (Throwable $e) {
                $results['errors'][] = "Service registration failed: " . $e->getMessage();
            }
        }

        return $results;
    }

    private function copyDirectoryContents(string $source, string $dest): int
    {
        if (!$this->fileMeta->isDir($source)) {
            throw new RuntimeException("Source directory does not exist: {$source}");
        }

        if (!$this->fileMeta->isDir($dest)) {
            $this->fileManipulation->mkdir($dest, 0755, true);
        }

        $count = 0;
        $items = new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS);

        foreach ($items as $item) {
            $target = $dest . DIRECTORY_SEPARATOR . $item->getBasename();

            if ($item->isDir()) {
                $count += $this->copyDirectoryContents($item->getPathname(), $target);
            } else {
                if (!$this->fileManipulation->copy($item->getPathname(), $target)) {
                    throw new RuntimeException("Failed to copy file: {$item->getPathname()} to {$target}");
                }
                $count++;
            }
        }

        return $count;
    }

    public function registerProviders(array $providers): void
    {
        if (empty($providers)) {
            return;
        }

        $configFile = $this->paths->appPath('Config/providers.php');
        $content = $this->fileReadWrite->get($configFile);
        $pattern = "/(return\s*\[)([^\]]*?)(\];)/s";

        if (preg_match($pattern, $content, $matches)) {
            $pre = $matches[1];
            $existing = $matches[2];
            $post = $matches[3];
            $modified = false;

            foreach ($providers as $provider) {
                if (str_contains($content, $provider . '::class')) {
                    continue;
                }

                $newItem = "    {$provider}::class,";

                $trimmedExisting = trim($existing);

                if ($trimmedExisting === '') {
                    $existing = "\n" . $newItem . "\n";
                } else {
                    $existing = rtrim($existing);
                    if (!str_ends_with($existing, ',')) {
                        $existing .= ',';
                    }
                    $existing .= "\n" . $newItem . "\n";
                }
                $modified = true;
            }

            if ($modified) {
                $replacement = $pre . $existing . $post;
                $content = str_replace($matches[0], $replacement, $content);
                $this->fileReadWrite->put($configFile, $content);
            }
        }
    }

    public function registerMiddleware(array $middlewares): void
    {
        $configFile = $this->paths->appPath('Config/middleware.php');
        $content = $this->fileReadWrite->get($configFile);

        foreach ($middlewares as $group => $classes) {
            foreach ($classes as $class) {
                if (str_contains($content, $class . '::class')) {
                    continue;
                }

                $pattern = "/('$group'\s*=>\s*\[)([^\]]*?)(\])/s";

                if (preg_match($pattern, $content, $matches)) {
                    $pre = $matches[1];
                    $existing = $matches[2];
                    $post = $matches[3];

                    $newItem = "        {$class}::class,";
                    $trimmedExisting = trim($existing);

                    if ($trimmedExisting === '') {
                        $newContent = "\n" . $newItem . "\n    ";
                    } else {
                        $existing = rtrim($existing);

                        if (!str_ends_with($existing, ',')) {
                            $existing .= ',';
                        }

                        $newContent = $existing . "\n" . $newItem . "\n    ";
                    }

                    $replacement = $pre . $newContent . $post;
                    $content = str_replace($matches[0], $replacement, $content);
                }
            }
        }

        $this->fileReadWrite->put($configFile, $content);
    }

    public function checkStatus(string $packagePath, array $manifest = []): string
    {
        // Check Configs
        $configSource = $packagePath . DIRECTORY_SEPARATOR . 'Config';
        if ($this->fileMeta->isDir($configSource)) {
            $files = scandir($configSource);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (!$this->fileMeta->exists($this->paths->appPath('Config/' . $file))) {
                    return self::STATUS_MISSING_FILES;
                }
            }
        }

        // Check Migrations
        $migrationSource = $packagePath . DIRECTORY_SEPARATOR . 'Database/Migrations';
        if ($this->fileMeta->isDir($migrationSource)) {
            $files = scandir($migrationSource);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (!$this->fileMeta->exists($this->paths->storagePath('database/migrations/' . $file))) {
                    return self::STATUS_MISSING_FILES;
                }
            }
        }

        $filesChecked = 0;
        $filesFound = 0;

        $checkDir = function ($source, $destCallback) use (&$filesChecked, &$filesFound) {
            if ($this->fileMeta->isDir($source)) {
                $files = scandir($source);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    $filesChecked++;
                    if ($this->fileMeta->exists($destCallback($file))) {
                        $filesFound++;
                    }
                }
            }
        };

        $checkDir($configSource, fn ($f) => $this->paths->appPath('Config/' . $f));
        $checkDir($migrationSource, fn ($f) => $this->paths->storagePath('database/migrations/' . $f));

        if ($filesChecked === 0) {
            return self::STATUS_NOT_INSTALLED;
        }

        if ($filesFound === 0) {
            return self::STATUS_NOT_INSTALLED;
        }

        if ($filesFound < $filesChecked) {
            return self::STATUS_MISSING_FILES;
        }

        return self::STATUS_INSTALLED;
    }

    public function uninstall(string $packagePath, array $manifest = []): void
    {
        // Rollback & Delete Migrations
        $migrationSource = $packagePath . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';
        $migrationDest = $this->paths->storagePath('database/migrations');

        if ($this->fileMeta->isDir($migrationSource)) {
            try {
                $connection = resolve(ConnectionInterface::class);
                $this->migrator = new Migrator($connection, [$migrationDest, $migrationSource]);
            } catch (Throwable $e) {
                error_log("PackageManager: Failed to initialize Migrator: " . $e->getMessage());
            }

            $filesToDelete = [];
            $this->collectFiles($migrationSource, $migrationDest, $filesToDelete);

            usort($filesToDelete, fn ($a, $b) => strcmp($b['basename'], $a['basename']));

            foreach ($filesToDelete as $fileInfo) {
                $target = $fileInfo['target'];
                if ($this->migrator) {
                    $migrationName = pathinfo($fileInfo['basename'], PATHINFO_FILENAME);
                    try {
                        $this->migrator->rollbackFile($migrationName);
                    } catch (Throwable $e) {
                        error_log("Failed to rollback migration {$migrationName}: " . $e->getMessage());
                    }
                }

                if ($this->fileMeta->exists($target)) {
                    $this->fileManipulation->delete($target);
                }

                $dir = dirname($target);
                $this->cleanupEmptyDirectory($dir);
            }
        }

        // Delete Configs
        $configSource = $packagePath . DIRECTORY_SEPARATOR . 'Config';
        $configDest = $this->paths->appPath('Config');

        if ($this->fileMeta->isDir($configSource)) {
            $this->removeDirectoryContents($configSource, $configDest);
        }

        // Unregister Services
        if (!empty($manifest)) {
            if (isset($manifest['providers'])) {
                $this->unregisterProviders($manifest['providers']);
            }

            if (isset($manifest['middleware'])) {
                $this->unregisterMiddleware($manifest['middleware']);
            }
        }
    }

    private function removeDirectoryContents(string $source, string $dest): void
    {
        if (!$this->fileMeta->isDir($source)) {
            return;
        }

        $items = new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS);

        foreach ($items as $item) {
            $target = $dest . DIRECTORY_SEPARATOR . $item->getBasename();

            if ($item->isDir()) {
                $this->removeDirectoryContents($item->getPathname(), $target);
                $this->cleanupEmptyDirectory($target);
            } else {
                if ($this->fileMeta->exists($target)) {
                    $this->fileManipulation->delete($target);
                }
            }
        }
    }

    private function cleanupEmptyDirectory(string $dir): void
    {
        if ($this->fileMeta->isDir($dir)) {
            $files = scandir($dir);
            if (count($files) <= 2) {
                try {
                    $this->fileManipulation->delete($dir);
                } catch (Throwable) {
                    // Silently ignore - directory may have been deleted already
                }
            }
        }
    }

    private function collectFiles(string $source, string $baseDest, array &$collection): void
    {
        if (!$this->fileMeta->isDir($source)) {
            return;
        }

        $items = new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS);

        foreach ($items as $item) {
            $target = $baseDest . DIRECTORY_SEPARATOR . $item->getBasename();

            if ($item->isDir()) {
                $this->collectFiles($item->getPathname(), $target, $collection);
            } else {
                $collection[] = [
                    'source' => $item->getPathname(),
                    'target' => $target,
                    'basename' => $item->getBasename()
                ];
            }
        }
    }

    private function unregisterProviders(array $providers): void
    {
        $configFile = $this->paths->appPath('Config/providers.php');
        $lines = file($configFile);
        $newLines = [];

        foreach ($lines as $line) {
            $keep = true;
            foreach ($providers as $provider) {
                if (str_contains($line, $provider . '::class')) {
                    $keep = false;
                    break;
                }
            }

            if ($keep) {
                $newLines[] = $line;
            }
        }

        $this->fileReadWrite->put($configFile, implode('', $newLines));
    }

    private function unregisterMiddleware(array $middlewares): void
    {
        $configFile = $this->paths->appPath('Config/middleware.php');
        $lines = file($configFile);
        $newLines = [];

        foreach ($lines as $line) {
            $keep = true;
            foreach ($middlewares as $group => $classes) {
                foreach ($classes as $class) {
                    if (str_contains($line, $class . '::class')) {
                        $keep = false;
                        break 2;
                    }
                }
            }
            if ($keep) {
                $newLines[] = $line;
            }
        }

        $this->fileReadWrite->put($configFile, implode('', $newLines));
    }
}
