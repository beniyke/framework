<?php

/**
 * Anchor Framework
 *
 * File system helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

declare(strict_types=1);

use Helpers\File\Contracts\CacheInterface;
use Helpers\File\FileSystem;
use Helpers\File\FileUploadValidator;
use Helpers\File\ImageHelper;
use Helpers\File\Mimes;

if (! function_exists('filesystem')) {
    function filesystem(): FileSystem
    {
        return new FileSystem();
    }
}

if (! function_exists('mimes')) {
    function mimes(): Mimes
    {
        return new Mimes();
    }
}

if (! function_exists('cache')) {
    function cache(string $path): CacheInterface
    {
        return resolve(CacheInterface::class)->withPath($path);
    }
}

if (! function_exists('image')) {
    function image(?string $image_path = null): ImageHelper
    {
        $helper = new ImageHelper();

        return empty($image_path) ? $helper : $helper->image($image_path);
    }
}

if (! function_exists('validate_upload')) {
    function validate_upload(array $file, array $options): bool
    {
        $type = $options['type'] ?? null;
        $maxSize = $options['maxSize'] ?? 5242880;

        if ($type === 'image') {
            $validator = FileUploadValidator::forImages($maxSize);
        } elseif ($type === 'document') {
            $validator = FileUploadValidator::forDocuments($maxSize);
        } elseif ($type === 'archive') {
            $validator = FileUploadValidator::forArchives($maxSize);
        } else {
            $validator = new FileUploadValidator(
                $options['mimeTypes'] ?? [],
                $options['extensions'] ?? [],
                $maxSize
            );
        }

        try {
            return $validator->validate($file);
        } catch (RuntimeException $e) {
            return false;
        }
    }
}

if (! function_exists('upload_image')) {
    function upload_image(array $file, string $destination, int $maxSize = 5242880): string
    {
        $validator = FileUploadValidator::forImages($maxSize);

        return $validator->moveUploadedFile($file, $destination);
    }
}

if (! function_exists('upload_document')) {
    function upload_document(array $file, string $destination, int $maxSize = 10485760): string
    {
        $validator = FileUploadValidator::forDocuments($maxSize);

        return $validator->moveUploadedFile($file, $destination);
    }
}

if (! function_exists('upload_archive')) {
    function upload_archive(array $file, string $destination, int $maxSize = 52428800): string
    {
        $validator = FileUploadValidator::forArchives($maxSize);

        return $validator->moveUploadedFile($file, $destination);
    }
}
