<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class saves email content to disk for debugging purposes.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Services;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

class MailDebugSaver
{
    private string $storagePath;

    public function __construct(string $storagePath)
    {
        $this->storagePath = $storagePath;
    }

    public function save(string $filename, string $content): void
    {
        $directory = Paths::basePath($this->storagePath);
        FileSystem::mkdir($directory);

        $fullPath = Paths::basePath($this->storagePath . '/' . $filename);

        if (FileSystem::exists($fullPath)) {
            FileSystem::delete($fullPath);
        }

        FileSystem::put($fullPath, $content);
    }
}
