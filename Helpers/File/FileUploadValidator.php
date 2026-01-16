<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Validates uploaded files against security rules.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File;

use RuntimeException;

class FileUploadValidator
{
    private array $allowedMimeTypes = [];

    private array $allowedExtensions = [];

    private int $maxFileSize = 5242880; // 5MB default

    private bool $requireMimeMatch = true;

    public function __construct(array $allowedMimeTypes = [], array $allowedExtensions = [], int $maxFileSize = 5242880)
    {
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->allowedExtensions = $allowedExtensions;
        $this->maxFileSize = $maxFileSize;
    }

    public function validate(array $file): bool
    {
        if (! isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid file upload parameters.');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('File size exceeds limit.');
            case UPLOAD_ERR_PARTIAL:
                throw new RuntimeException('File was only partially uploaded.');
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('No file was uploaded.');
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new RuntimeException('Missing temporary folder.');
            case UPLOAD_ERR_CANT_WRITE:
                throw new RuntimeException('Failed to write file to disk.');
            case UPLOAD_ERR_EXTENSION:
                throw new RuntimeException('File upload stopped by extension.');
            default:
                throw new RuntimeException('Unknown upload error.');
        }

        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            throw new RuntimeException(sprintf(
                'File size (%d bytes) exceeds maximum allowed size (%d bytes).',
                $file['size'],
                $this->maxFileSize
            ));
        }

        // Validate MIME type
        if (! empty($this->allowedMimeTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (! in_array($mimeType, $this->allowedMimeTypes, true)) {
                throw new RuntimeException(sprintf(
                    'File MIME type "%s" is not allowed. Allowed types: %s',
                    $mimeType,
                    implode(', ', $this->allowedMimeTypes)
                ));
            }
        }

        // Validate Extension
        if (! empty($this->allowedExtensions)) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (! in_array($extension, $this->allowedExtensions, true)) {
                throw new RuntimeException(sprintf(
                    'File extension "%s" is not allowed. Allowed extensions: %s',
                    $extension,
                    implode(', ', $this->allowedExtensions)
                ));
            }
        }

        return true;
    }

    public function generateSafeFilename(string $originalName, bool $preserveExtension = true): string
    {
        $randomName = bin2hex(random_bytes(16));

        if ($preserveExtension) {
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension) {
                return $randomName . '.' . $extension;
            }
        }

        return $randomName;
    }

    /**
     * Move uploaded file to destination with validation
     */
    public function moveUploadedFile(array $file, string $destination, bool $generateSafeName = true): string
    {
        $this->validate($file);

        // Ensure destination directory exists
        $destinationDir = dirname($destination);
        if (! is_dir($destinationDir)) {
            if (! mkdir($destinationDir, 0755, true)) {
                throw new RuntimeException('Failed to create destination directory.');
            }
        }

        // Generate safe filename if requested
        if ($generateSafeName) {
            $safeFilename = $this->generateSafeFilename($file['name']);
            $destination = $destinationDir . DIRECTORY_SEPARATOR . $safeFilename;
        }

        // Move the file
        if (! move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        return $destination;
    }

    /**
     * Create a validator for common file types
     */
    public static function forImages(int $maxSize = 5242880): self
    {
        return new self(
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            $maxSize
        );
    }

    public static function forDocuments(int $maxSize = 10485760): self
    {
        return new self(
            ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['pdf', 'doc', 'docx'],
            $maxSize
        );
    }

    public static function forArchives(int $maxSize = 52428800): self
    {
        return new self(
            ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'],
            ['zip', 'rar', '7z'],
            $maxSize
        );
    }
}
