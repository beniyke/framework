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

use Core\Events\ConsoleTerminateEvent;
use Core\Ioc\ContainerInterface;
use Core\Services\ConfigServiceInterface;
use Core\Support\Adapters\Interfaces\SapiInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Throwable;

class Console
{
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

    private readonly ConfigServiceInterface $config;

    private array $excluded = ['AbstractCommand'];

    public function __construct(
        PathResolverInterface $paths,
        FileMetaInterface $fileMeta,
        FileReadWriteInterface $fileReadWrite,
        SapiInterface $sapi,
        ContainerInterface $container,
        ConfigServiceInterface $config
    ) {
        $this->paths = $paths;
        $this->fileMeta = $fileMeta;
        $this->fileReadWrite = $fileReadWrite;
        $this->sapi = $sapi;
        $this->container = $container;
        $this->config = $config;

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
        Event::dispatch(new ConsoleTerminateEvent());
    }

    private function ensureCliEnvironment(): void
    {
        if ($this->sapi->isCgi()) {
            exit("This CLI tool requires the PHP CLI (not php-cgi)." . PHP_EOL);
        }
    }

    private function discoverCommands(): array
    {
        $commands = [];

        // Discover System Commands (System/*/Commands)
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

        // Discover CLI Commands (System/Cli/Commands/*)
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

        // Discover (Internal) App Commands (App/src/*/Commands)
        $appSourcePath = $this->paths->appSourcePath();
        if ($this->fileMeta->isDir($appSourcePath)) {
            $appSrcModules = $this->discoverSubdirectories($appSourcePath);
            foreach ($appSrcModules as $module) {
                $moduleCommandsPath = $appSourcePath . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'Commands';
                if ($this->fileMeta->isDir($moduleCommandsPath)) {
                    foreach ($this->discoverClassNames($moduleCommandsPath) as $class) {
                        $fqcn = $this->qualify("{$module}\\Commands\\{$class}");
                        if ($this->isValidCommand($fqcn)) {
                            $commands[] = $fqcn;
                        }
                    }
                }
            }
        }

        // Discover App Commands (App/Commands)
        $appCommandsPath = $this->paths->appPath('Commands');
        foreach ($this->discoverClassNames($appCommandsPath) as $class) {
            $fqcn = $this->qualify("App\\Commands\\{$class}");
            if ($this->isValidCommand($fqcn)) {
                $commands[] = $fqcn;
            }
        }

        // Discover Custom Package Commands (packages/*/Commands)
        $packagesPath = $this->paths->basePath('packages');
        if ($this->fileMeta->isDir($packagesPath)) {
            $packages = $this->discoverSubdirectories($packagesPath);

            foreach ($packages as $package) {
                $packagePath = $packagesPath . DIRECTORY_SEPARATOR . $package;
                $packageCommandsPath = $packagePath . DIRECTORY_SEPARATOR . 'Commands';

                if ($this->fileMeta->isDir($packageCommandsPath)) {
                    // Filter commands if the package is not registered
                    if (! $this->isPackageActive($packagePath)) {
                        continue;
                    }

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

        return array_filter(scandir($path), fn ($item) => $item !== '.' && $item !== '..' && $this->fileMeta->isDir($path . DIRECTORY_SEPARATOR . $item));
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

    private function isPackageActive(string $packagePath): bool
    {
        $setupFile = $packagePath . DIRECTORY_SEPARATOR . 'setup.php';

        if (! $this->fileMeta->isFile($setupFile)) {
            return true; // No setup file, assume active or it's just a dir
        }

        try {
            $manifest = require $setupFile;

            if (! is_array($manifest) || ! isset($manifest['providers']) || empty($manifest['providers'])) {
                return true;
            }

            $registeredProviders = $this->config->get('providers', []);

            foreach ($manifest['providers'] as $provider) {
                if (in_array($provider, $registeredProviders, true)) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return true; // Safety fallback
        }
    }
}
