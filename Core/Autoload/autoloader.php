<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Core Autoloader.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

require_once __DIR__ . '/DirectoryDiscovery.php';

class Autoloader
{
    private readonly string $basepath;

    private readonly DirectoryDiscovery $directoryDiscovery;

    public function __construct(DirectoryDiscovery $directoryDiscovery)
    {
        $this->basepath = $directoryDiscovery->getBasePath();
        $this->directoryDiscovery = $directoryDiscovery;
    }

    public function init(): void
    {
        spl_autoload_register([$this, 'autoload'], true, false);
    }

    public function loadComposerAutoload(): void
    {
        $vendorPath = $this->basepath . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        if (is_file($vendorPath)) {
            require_once $vendorPath;
        }
    }

    public function autoload(string $classname): void
    {
        $classname = $this->resolveClassname($classname);
        $this->require(str_replace('\\', DIRECTORY_SEPARATOR, $classname));
    }

    private function require(string $filepath, bool $with_basepath = true): void
    {
        if (is_file($filepath = ($with_basepath ? $this->basepath : '') . "$filepath.php")) {
            require_once $filepath;
        }
    }

    private function resolveClassname(string $classname): string
    {
        $parts = explode('\\', $classname);
        $root_namespace = $parts[0];

        if ($root_namespace === 'Carbon') {
            return $this->directoryDiscovery->getMapValue('libs') . str_replace('Carbon\\', $this->directoryDiscovery->getMapValue('carbon'), $classname);
        }

        if ($this->directoryDiscovery->isSystemNamespace($root_namespace)) {
            return $this->directoryDiscovery->getMapValue('system') . $classname;
        }

        if ($this->directoryDiscovery->isLibNamespace($root_namespace)) {
            return $this->directoryDiscovery->getMapValue('libs') . $classname;
        }

        if ($root_namespace === 'App') {
            return $this->directoryDiscovery->resolveAppNamespace($classname);
        }

        return $this->directoryDiscovery->getMapValue('packages') . $classname;
    }
}
