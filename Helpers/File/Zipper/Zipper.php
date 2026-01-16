<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Zipper Class: Provides robust file archiving and extraction functionality.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Zipper;

use Exception;
use Helpers\File\FileSystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class Zipper
{
    private ZipArchive $zip;

    private ?string $file = null;

    private ?string $path = null;

    private array $files = [];

    private bool $setPassword = false;

    private ?string $password = null;

    private const DEFAULT_DIR_PERMISSIONS = 0777;

    public function __construct()
    {
        if (! extension_loaded('zip')) {
            error_log('FATAL: The PHP ZipArchive extension is not enabled. Zipper class cannot function.');
        }

        $this->zip = new ZipArchive();
    }

    private function getZipErrorMessage(int $status): string
    {
        return match ($status) {
            ZipArchive::ER_EXISTS => 'File already exists.',
            ZipArchive::ER_INVAL => 'Invalid argument (e.g., file name too long).',
            ZipArchive::ER_NOENT => 'No such file.',
            ZipArchive::ER_OPEN => 'Can\'t open file.',
            ZipArchive::ER_READ => 'Read error.',
            ZipArchive::ER_WRITE => 'Write error.',
            ZipArchive::ER_ZIPCLOSED => 'Zip Archive is already closed.',
            default => 'Unknown ZipArchive error (Code: ' . $status . ')',
        };
    }

    private function validateAndCreatePath(string $path, string $context): bool|string
    {
        if (empty($path)) {
            return "{$context} path is missing.";
        }

        if (! FileSystem::isDir($path)) {
            if (! FileSystem::mkdir($path, self::DEFAULT_DIR_PERMISSIONS, true) && ! FileSystem::isDir($path)) {
                return "The {$context} path '{$path}' could not be created or is unwritable.";
            }
        }

        return true;
    }

    public function password(string $password): self
    {
        $this->setPassword = true;
        $this->password = $password;

        return $this;
    }

    public function add(array $files): self
    {
        $this->files = [];
        foreach ($files as $file) {
            if (is_string($file)) {
                $this->files[] = $file;
            }
        }

        return $this;
    }

    public function file(string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function path(string $path): self
    {
        $this->path = rtrim($path, '/\\');

        return $this;
    }

    public function save(string $name): Result
    {
        $pathCheck = $this->validateAndCreatePath($this->path ?? '', 'Zip destination');

        if (is_string($pathCheck)) {
            return Result::error('Zip creation failed: ' . $pathCheck);
        }

        if (empty($this->files)) {
            return Result::error('Zip creation failed: Cannot find files to be zipped.');
        }

        $zip_path = $this->path . DIRECTORY_SEPARATOR . $name;
        $files_to_add = [];
        $not_exist = [];

        foreach ($this->files as $file) {
            if (is_file($file)) {
                $files_to_add[] = $file;
            } else {
                $not_exist[] = $file;
            }
        }

        if (empty($files_to_add)) {
            $message = 'Zip creation failed: No valid files to add. The file(s) [' . implode(', ', $not_exist) . '] do not exist or are not files.';

            return Result::error($message);
        }

        $zip_mode = is_file($zip_path) ? ZipArchive::OVERWRITE : ZipArchive::CREATE;
        if ($this->zip->open($zip_path, $zip_mode) !== true) {
            $message = 'Could not open/create zip: ' . $this->getZipErrorMessage($this->zip->status);

            return Result::error($message);
        }

        if ($this->setPassword && $this->password) {
            $this->zip->setArchiveStream('aes_settings', ZipArchive::EM_AES_256);
        }

        foreach ($files_to_add as $file) {
            $filename = basename($file);
            if ($this->zip->addFile($file, $filename) === false) {
                error_log("Zipper::save - Failed to add file '{$file}' to archive '{$zip_path}'.");

                continue;
            }
            if ($this->setPassword && $this->password) {
                $this->zip->setEncryptionName($filename, ZipArchive::EM_AES_256, $this->password);
            }
        }

        if ($this->zip->close() === false) {
            $message = 'Zip creation failed: Failed to finalize and close zip archive. ' . $this->getZipErrorMessage($this->zip->status);

            return Result::error($message);
        }

        $success_message = $name . ' successfully created.' . (! empty($not_exist) ? ' Note: Missing files: ' . implode(', ', $not_exist) : '');

        return Result::success($success_message, ['zip_path' => $zip_path, 'files_added' => count($files_to_add)]);
    }

    public function extract(): Result
    {
        if (empty($this->file) || ! is_file($this->file)) {
            return Result::error('Extraction failed: The zip file to be extracted is missing or does not exist.');
        }

        $pathCheck = $this->validateAndCreatePath($this->path ?? '', 'Extraction destination');
        if (is_string($pathCheck)) {
            return Result::error('Extraction failed: ' . $pathCheck);
        }

        if ($this->zip->open($this->file) !== true) {
            $message = 'Could not open zip file: ' . $this->getZipErrorMessage($this->zip->status);

            return Result::error($message);
        }

        if ($this->setPassword && $this->password) {
            $this->zip->setPassword($this->password);
        }

        $extracted_files = [];
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            if ($stat !== false) {
                $extracted_files[] = $stat['name'];
            }
        }

        if ($this->zip->extractTo($this->path) !== true) {
            $this->zip->close();
            $message = 'Extraction failed. Check file permissions, disk space, or verify the password for an encrypted archive.';

            return Result::error($message);
        }

        $this->zip->close();

        return Result::success('Files extracted successfully.', ['destination' => $this->path, 'files' => $extracted_files]);
    }

    public function zipData(array $paths, string $zipFilePath): bool
    {
        $zipDir = dirname($zipFilePath);
        $pathCheck = $this->validateAndCreatePath($zipDir, 'Zip output directory');
        if (is_string($pathCheck)) {
            error_log('Zipper::zipData failed: ' . $pathCheck);

            return false;
        }

        if ($this->zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            error_log("Zipper::zipData failed: Could not open/create the zip file at '{$zipFilePath}'.");

            return false;
        }

        if ($this->setPassword && $this->password) {
            $this->zip->setArchiveStream('aes_settings', ZipArchive::EM_AES_256);
        }

        foreach ($paths as $path) {
            if (! is_string($path) || ! file_exists($path)) {
                continue;
            }

            $rootPath = realpath($path);

            if (is_file($rootPath)) {
                $filename = basename($rootPath);
                $this->zip->addFile($rootPath, $filename);
                if ($this->setPassword && $this->password) {
                    $this->zip->setEncryptionName($filename, ZipArchive::EM_AES_256, $this->password);
                }

                continue;
            }

            if (is_dir($rootPath)) {
                $parentPath = dirname($rootPath);
                try {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                } catch (Exception $e) {
                    error_log("Zipper::zipData recursive iteration failed for '{$rootPath}': " . $e->getMessage());

                    continue;
                }

                foreach ($files as $file) {
                    if ($file->isDir()) {
                        continue;
                    }

                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($parentPath) + 1);

                    $this->zip->addFile($filePath, $relativePath);

                    if ($this->setPassword && $this->password) {
                        $this->zip->setEncryptionName($relativePath, ZipArchive::EM_AES_256, $this->password);
                    }
                }
            }
        }

        if ($this->zip->close() === false) {
            error_log('Zipper::zipData failed: Failed to finalize and close zip archive.');

            return false;
        }

        return true;
    }
}
