<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Console serves as the entry point for the CLI application.
 * It is now fully decoupled and relies entirely on injected interfaces.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core;

use Core\Ioc\ContainerInterface;
use Core\Support\Adapters\Interfaces\SapiInterface;
use Defer\DeferredTaskTrait;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Throwable;

class Console
{
    use DeferredTaskTrait;

    private const NAME = 'Anchor Console (Dock) by BenIyke';

    public function getVersion(): string
    {
        return App::VERSION;
    }

    private readonly PathResolverInterface $paths;

    private readonly FileMetaInterface $fileMeta;

    private readonly FileReadWriteInterface $fileReadWrite;

    private readonly SapiInterface $sapi;

    private readonly ContainerInterface $container;

    private array $excluded = ['AbstractCommand'];

    public function __construct(PathResolverInterface $paths, FileMetaInterface $fileMeta, FileReadWriteInterface $fileReadWrite, SapiInterface $sapi, ContainerInterface $container)
    {
        $this->paths = $paths;
        $this->fileMeta = $fileMeta;
        $this->fileReadWrite = $fileReadWrite;
        $this->sapi = $sapi;
        $this->container = $container;

        $this->ensureCliEnvironment();
    }

    public function run(): void
    {
        $app = new Application(self::NAME, $this->getVersion());

        foreach ($this->discoverCommands() as $commandClass) {
            if (class_exists($commandClass)) {
                try {
                    $app->add($this->container->get($commandClass));
                } catch (Throwable $e) {
                    try {
                        $app->add(new $commandClass());
                    } catch (Throwable $legacyError) {
                        echo "Failed to register command: {$commandClass}. Error: {$e->getMessage()}\n";
                    }
                }
            }
        }

        $app->run();
        $this->executeDeferredTasks();
    }

    private function ensureCliEnvironment(): void
    {
        if ($this->sapi->isCgi()) {
            exit("This CLI tool requires the PHP CLI (not php-cgi).\n");
        }
    }

    private function discoverCommands(): array
    {
        $commands = [];

        // 1. Discover System Module Commands (System/*/Commands)
        $systemPath = $this->paths->systemPath();
        $systemModules = $this->discoverSubdirectories($systemPath);

        foreach ($systemModules as $module) {
            $moduleCommandsPath = $this->paths->systemPath($module . DIRECTORY_SEPARATOR . 'Commands');

            if ($this->fileMeta->isDir($moduleCommandsPath)) {
                foreach ($this->discoverClassNames($moduleCommandsPath) as $class) {
                    $fqcn = $this->qualify("{$module}\\Commands\\{$class}");
                    if ($this->isValidCommand($fqcn)) {
                        $commands[] = $fqcn;
                    }
                }
            }
        }

        // 2. Discover CLI Commands (System/Cli/Commands/*)
        $cliCommandsPath = $this->paths->cliPath('Commands');
        $cli_directories = $this->discoverSubdirectories($cliCommandsPath);

        foreach ($cli_directories as $dir) {
            $subDirPath = $cliCommandsPath . DIRECTORY_SEPARATOR . $dir;

            foreach ($this->discoverClassNames($subDirPath) as $class) {
                $fqcn = $this->qualify("Cli\\Commands\\{$dir}\\{$class}");
                if ($this->isValidCommand($fqcn)) {
                    $commands[] = $fqcn;
                }
            }
        }

        // 3. Discover App Commands (App/Commands)
        $appCommandsPath = $this->paths->appPath('Commands');
        foreach ($this->discoverClassNames($appCommandsPath) as $class) {
            $fqcn = $this->qualify("App\\Commands\\{$class}");
            if ($this->isValidCommand($fqcn)) {
                $commands[] = $fqcn;
            }
        }

        // 4. Discover Custom Package Commands (packages/*/Commands)
        $packagesPath = $this->paths->basePath('packages');
        if ($this->fileMeta->isDir($packagesPath)) {
            $packages = $this->discoverSubdirectories($packagesPath);

            foreach ($packages as $package) {
                $packageCommandsPath = $packagesPath . DIRECTORY_SEPARATOR . $package . DIRECTORY_SEPARATOR . 'Commands';

                if ($this->fileMeta->isDir($packageCommandsPath)) {
                    foreach ($this->discoverClassNames($packageCommandsPath) as $class) {
                        $fqcn = $this->qualify("{$package}\\Commands\\{$class}");
                        if ($this->isValidCommand($fqcn)) {
                            $commands[] = $fqcn;
                        }
                    }
                }
            }
        }

        return $commands;
    }

    private function isValidCommand(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        if (!is_subclass_of($class, Command::class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return false;
        }

        return true;
    }

    private function discoverSubdirectories(string $path): array
    {
        if (! $this->fileMeta->isDir($path)) {
            return [];
        }

        return array_filter(
            scandir($path),
            fn ($item) => $item !== '.' && $item !== '..' && $this->fileMeta->isDir($path . DIRECTORY_SEPARATOR . $item)
        );
    }

    private function discoverClassNames(string $path): array
    {
        $files = $this->fileReadWrite->getDirectoryContents($path);

        $classes = [];

        if ($files) {
            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $class = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                if (! in_array($class, $this->excluded)) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    private function qualify(string $fqcn): string
    {
        return str_replace('/', '\\', $fqcn);
    }
}
