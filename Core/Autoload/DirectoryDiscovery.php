<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class provides basic directory-to-namespace mapping and discovery
 * for a custom autoloader setup.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */
class DirectoryDiscovery
{
    private const NAMESPACE_MAP = [
        'system' => 'System\\',
        'packages' => 'packages\\',
        'libs' => 'libs\\',
        'app' => 'App\\',
        'src' => 'App\\src\\',
        'carbon' => 'Carbon\\src\\Carbon\\',
    ];

    private array $discoveredDirectoriesCache = [];

    private readonly string $basepath;

    public function __construct(string $basepath)
    {
        $this->basepath = rtrim($basepath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function getBasePath(): string
    {
        return $this->basepath;
    }

    public function getMapValue(string $key): string
    {
        return self::NAMESPACE_MAP[$key] ?? '';
    }

    public function isSystemNamespace(string $root_namespace): bool
    {
        return in_array($root_namespace, $this->discover('system'), true);
    }

    public function isLibNamespace(string $root_namespace): bool
    {
        return in_array($root_namespace, $this->discover('libs'), true);
    }

    public function resolveAppNamespace(string $classname): string
    {
        $app_source_paths = $this->discover('src');
        $namespace_map = $this->buildNamespaceMap($app_source_paths);

        return str_replace(array_keys($namespace_map), array_values($namespace_map), $classname);
    }

    private function discover(string $key): array
    {
        if (isset($this->discoveredDirectoriesCache[$key])) {
            return $this->discoveredDirectoriesCache[$key];
        }

        $directory_path = $this->basepath . str_replace('\\', DIRECTORY_SEPARATOR, self::NAMESPACE_MAP[$key] ?? '');

        $directories = array_map('basename', glob($directory_path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR));

        return $this->discoveredDirectoriesCache[$key] = $directories;
    }

    private function buildNamespaceMap(array $app_source_paths): array
    {
        $namespace_map = [];
        foreach ($app_source_paths as $path) {
            $namespace_map[self::NAMESPACE_MAP['app'] . $path] = self::NAMESPACE_MAP['src'] . $path;
        }

        return $namespace_map;
    }
}
