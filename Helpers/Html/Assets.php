<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Helper class for managing and resolving asset URLs.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Html;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

class Assets
{
    public function url(?string $file = null): string
    {
        if (is_string($file)) {
            $file = trim($file);
        }

        if (empty($file)) {
            return '';
        }

        if ((str_starts_with($file, '//') && ! str_starts_with($file, '///')) || filter_var($file, FILTER_VALIDATE_URL)) {
            return $file;
        }

        $cleanFile = ltrim($file, '/');
        $fullPath = Paths::publicPath('assets/' . $cleanFile);

        if (FileSystem::exists($fullPath)) {
            $timestamp = FileSystem::lastModified($fullPath);
            $cleanFile = $cleanFile . '?' . $timestamp;
        }

        return url('public/assets/' . $cleanFile);
    }
}
