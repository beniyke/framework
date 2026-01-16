<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Adapter for path resolution.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Adapters;

use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Helpers\File\Paths;

class PathResolverAdapter implements PathResolverInterface
{
    public function setBasePath(string $path): void
    {
        Paths::setBasePath($path);
    }

    public function basePath(?string $value = null): string
    {
        return Paths::basePath($value);
    }

    public function appPath(?string $value = null): string
    {
        return Paths::appPath($value);
    }

    public function systemPath(?string $value = null): string
    {
        return Paths::systemPath($value);
    }

    public function appSourcePath(?string $value = null): string
    {
        return Paths::appSourcePath($value);
    }

    public function corePath(?string $value = null): string
    {
        return Paths::corePath($value);
    }

    public function cliPath(?string $value = null): string
    {
        return Paths::cliPath($value);
    }

    public function layoutPath(?string $value = null, ?string $module = null): string
    {
        return Paths::layoutPath($value, $module);
    }

    public function viewPath(?string $value = null, ?string $module = null): string
    {
        return Paths::viewPath($value, $module);
    }

    public function templatePath(?string $value = null, ?string $module = null): string
    {
        return Paths::templatePath($value, $module);
    }

    public function configPath(?string $value = null): string
    {
        return Paths::configPath($value);
    }

    public function publicPath(?string $value = null): string
    {
        return Paths::publicPath($value);
    }

    public function storagePath(?string $value = null): string
    {
        return Paths::storagePath($value);
    }

    public function cachePath(?string $value = null): string
    {
        return Paths::cachePath($value);
    }

    public function coreViewPath(?string $value = null): string
    {
        return Paths::coreViewPath($value);
    }

    public function coreViewTemplatePath(?string $value = null): string
    {
        return Paths::coreViewTemplatePath($value);
    }
}
