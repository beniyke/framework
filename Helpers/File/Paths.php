<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Manages system and application directory paths.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File;

class Paths
{
    private static string $basePath;

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, DIRECTORY_SEPARATOR);
    }

    public static function basePath(?string $value = null): string
    {
        return self::$basePath . DIRECTORY_SEPARATOR . ($value ?? '');
    }

    public static function appPath(?string $value = null): string
    {
        return self::basePath('App' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function systemPath(?string $value = null): string
    {
        return self::basePath('System' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function appSourcePath(?string $value = null): string
    {
        return self::appPath('src' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function testPath(?string $value = null): string
    {
        return self::basePath('tests' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function corePath(?string $value = null): string
    {
        return self::systemPath('Core' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function cliPath(?string $value = null): string
    {
        return self::systemPath('Cli' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function layoutPath(?string $value = null, ?string $module = null): string
    {
        $path_segment = 'Views' . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR;

        $path = $module
            ? self::appSourcePath(ucfirst($module) . DIRECTORY_SEPARATOR . $path_segment)
            : self::appPath($path_segment);

        return $path . ($value ?? '');
    }

    public static function viewPath(?string $value = null, ?string $module = null): string
    {
        $path_segment = 'Views' . DIRECTORY_SEPARATOR;

        $path = $module
            ? self::appSourcePath(ucfirst($module) . DIRECTORY_SEPARATOR . $path_segment)
            : self::appPath($path_segment);

        return $path . ($value ?? '');
    }

    public static function templatePath(?string $value = null, ?string $module = null): string
    {
        $path_segment = 'Views' . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR;

        $path = $module
            ? self::appSourcePath(ucfirst($module) . DIRECTORY_SEPARATOR . $path_segment)
            : self::appPath($path_segment);

        return $path . ($value ?? '');
    }

    public static function configPath(?string $value = null): string
    {
        return self::appPath('Config' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function publicPath(?string $value = null): string
    {
        return self::basePath('public' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function storagePath(?string $value = null): string
    {
        return self::appPath('storage' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function cachePath(?string $value = null): string
    {
        return self::storagePath('cache' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function coreViewPath(?string $value = null): string
    {
        return self::corePath('Views' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function coreViewTemplatePath(?string $value = null): string
    {
        return self::coreViewPath('Templates' . DIRECTORY_SEPARATOR . ($value ?? ''));
    }

    public static function join(string ...$paths): string
    {
        return self::normalize(implode(DIRECTORY_SEPARATOR, $paths));
    }

    public static function normalize(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $path);
    }

    public static function basename(string $path): string
    {
        return basename($path);
    }

    public static function dirname(string $path): string
    {
        return dirname($path);
    }
}
