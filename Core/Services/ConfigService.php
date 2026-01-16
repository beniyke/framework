<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The ConfigService is responsible for loading, caching, and accessing
 * application configuration files.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Services;

use Core\Support\Adapters\Interfaces\EnvironmentInterface;
use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use SplFileInfo;

class ConfigService implements ConfigServiceInterface
{
    private array $configs = [];

    private readonly EnvironmentInterface $environment;

    private readonly PathResolverInterface $paths;

    private readonly FileMetaInterface $fileMeta;

    private readonly FileReadWriteInterface $fileReadWrite;

    private readonly FileManipulationInterface $fileManipulation;

    public function __construct(EnvironmentInterface $environment, PathResolverInterface $paths, FileMetaInterface $fileMeta, FileReadWriteInterface $fileReadWrite, FileManipulationInterface $fileManipulation)
    {
        $this->environment = $environment;
        $this->paths = $paths;
        $this->fileMeta = $fileMeta;
        $this->fileReadWrite = $fileReadWrite;
        $this->fileManipulation = $fileManipulation;

        $this->loadConfigs();
    }

    private function loadConfigs(): void
    {
        $cache_path = $this->paths->cachePath('config.cache');
        $config_dir_path = $this->paths->appPath('Config');

        if ($this->fileMeta->exists($cache_path) && $this->fileMeta->isReadable($cache_path)) {
            if (! $this->environment->isProduction()) {
                $cacheTime = filemtime($cache_path);
                $configFiles = $this->fileReadWrite->getDirectoryContents($config_dir_path);

                $needsRefresh = false;
                foreach ($configFiles as $fileinfo) {
                    if ($fileinfo instanceof SplFileInfo && $fileinfo->isFile()) {
                        if ($fileinfo->getMTime() > $cacheTime) {
                            $needsRefresh = true;
                            break;
                        }
                    }
                }

                if (! $needsRefresh) {
                    $this->configs = require $cache_path;

                    return;
                }
            } else {
                $this->configs = require $cache_path;

                return;
            }
        }

        $files = $this->fileReadWrite->getDirectoryContents($config_dir_path);

        foreach ($files as $fileinfo) {
            if (! $fileinfo instanceof SplFileInfo || ! $fileinfo->isFile()) {
                continue;
            }

            $file_name = pathinfo($fileinfo->getFileName(), PATHINFO_FILENAME);

            if ($file_name != 'functions') {
                $this->configs[$file_name] = (include $fileinfo->getPathName());
            }
        }

        $cacheDir = dirname($cache_path);
        $this->fileManipulation->mkdir($cacheDir, 0777, true);

        $cacheContent = '<?php return ' . var_export($this->configs, true) . ';';
        $this->fileReadWrite->put($cache_path, $cacheContent);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $configs = $this->configs;
        unset($configs['default']);
        $keys = explode('.', $key);

        $firstKey = $keys[0];

        if (array_key_exists($firstKey, $this->configs)) {
            $config = $this->configs[$firstKey];
            unset($keys[0]);
        } else {
            $config = $this->configs['default'] ?? [];
        }

        foreach ($keys as $k) {
            if (! is_array($config) || ! array_key_exists($k, $config)) {
                return $default;
            }
            $config = $config[$k];
        }

        return $config;
    }

    public function all(): array
    {
        return $this->configs;
    }

    public function isDebugEnabled(): bool
    {
        return (bool) $this->get('debug');
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $firstKey = array_shift($keys);

        if (empty($keys)) {
            $this->configs[$firstKey] = $value;

            return;
        }

        if (!isset($this->configs[$firstKey]) || !is_array($this->configs[$firstKey])) {
            $this->configs[$firstKey] = [];
        }

        $current = &$this->configs[$firstKey];

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }
}
