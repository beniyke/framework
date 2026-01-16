<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class loads email templates from the file system.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Services;

use Helpers\File\FileSystem;
use InvalidArgumentException;
use Mail\Contracts\TemplateLoaderInterface;

class FileTemplateLoader implements TemplateLoaderInterface
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function load(string $template): string
    {
        $filePath = rtrim($this->path, '/') . '/' . $template;

        if (! FileSystem::exists($filePath)) {
            throw new InvalidArgumentException("Email template not found at: {$filePath}");
        }

        return FileSystem::get($filePath);
    }
}
