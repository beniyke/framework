<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for path resolution.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Adapters\Interfaces;

interface PathResolverInterface
{
    public function setBasePath(string $path): void;

    public function basePath(?string $value = null): string;

    public function appPath(?string $value = null): string;

    public function systemPath(?string $value = null): string;

    public function appSourcePath(?string $value = null): string;

    public function corePath(?string $value = null): string;

    public function cliPath(?string $value = null): string;

    public function layoutPath(?string $value = null, ?string $module = null): string;

    public function viewPath(?string $value = null, ?string $module = null): string;

    public function templatePath(?string $value = null, ?string $module = null): string;

    public function configPath(?string $value = null): string;

    public function publicPath(?string $value = null): string;

    public function storagePath(?string $value = null): string;

    public function cachePath(?string $value = null): string;

    public function coreViewPath(?string $value = null): string;

    public function coreViewTemplatePath(?string $value = null): string;
}
