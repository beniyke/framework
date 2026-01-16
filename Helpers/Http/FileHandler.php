<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class provides functionality for handling files uploaded via HTTP.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http;

use Exception;
use Helpers\File\FileUploadValidator;
use Helpers\File\Mimes;
use RuntimeException;

class FileHandler
{
    protected array $file = [];

    protected ?string $validationError = null;

    private static array $fileKeys = ['error', 'name', 'size', 'tmp_name', 'type'];

    public function __construct(array $file)
    {
        $this->file = self::_fixFilesArray($file);
    }

    public function getFile(): array
    {
        return $this->file;
    }

    public function getClientOriginalName(): string
    {
        return $this->file['name'];
    }

    public function getClientMimeType(): string
    {
        return $this->file['type'];
    }

    public function getpathName(): string
    {
        return $this->file['tmp_name'];
    }

    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->getClientOriginalName(), PATHINFO_EXTENSION);
    }

    /**
     * Returns the extension based on the client mime type.
     *
     * This method uses the mime type as guessed by getClientMimeType()
     * to guess the file extension.
     */
    public function getExtension(): string
    {
        return Mimes::guessExtensionFromType($this->getClientMimeType(), $this->getClientOriginalExtension()) ?? '';
    }

    public function getClientSize(): int
    {
        return $this->file['size'];
    }

    public function getSize(): int
    {
        return $this->getClientSize();
    }

    public function getError(): int
    {
        return $this->file['error'];
    }

    public function move(string $target_path, ?string $name = null): bool
    {
        if ($this->getError()) {
            return false;
        }

        if (! $this->isValid()) {
            return false;
        }

        if (empty($name)) {
            $name = $this->getClientOriginalName();
            $extension = $this->getExtension();
        }

        if (! empty($name)) {
            $file_extenstion = self::_extension($name);
            $extension = empty($file_extenstion) ? $this->getExtension() : $file_extenstion;
            $name = str_replace('.' . $extension, '', $name);
        }

        $name = self::_sanitize($name);

        $file = $name . '.' . $extension;

        if (! file_exists($target_path . '/')) {
            mkdir($target_path, 0777, true);
        }

        $destination = $target_path . '/' . $file;

        try {
            return move_uploaded_file($this->getpathName(), $destination);
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    private static function _fixFilesArray($data): array
    {
        if (! is_array($data)) {
            return $data;
        }

        $keys = array_keys($data);
        sort($keys);

        if (self::$fileKeys != $keys || ! isset($data['name']) || ! is_array($data['name'])) {
            return $data;
        }

        $files = $data;

        foreach (self::$fileKeys as $k) {
            unset($files[$k]);
        }

        foreach ($data['name'] as $key => $name) {
            $files[$key] = self::_fixFilesArray([
                'error' => $data['error'][$key],
                'name' => $name,
                'type' => $data['type'][$key],
                'tmp_name' => $data['tmp_name'][$key],
                'size' => $data['size'][$key],
            ]);
        }

        return $files;
    }

    /**
     * Returns the maximum size of an uploaded file as configured in php.ini.
     */
    public static function getMaxFilesize(): int
    {
        $iniMax = strtolower(ini_get('upload_max_filesize'));

        if ($iniMax === '') {
            return PHP_INT_MAX;
        }

        $max = ltrim($iniMax, '+');

        if (strpos($max, '0x') === 0) {
            $max = intval($max, 16);
        } elseif (strpos($max, '0') === 0) {
            $max = intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($iniMax, -1)) {
            case 't':
                $max *= 1024;
                // no break
            case 'g':
                $max *= 1024;
                // no break
            case 'm':
                $max *= 1024;
                // no break
            case 'k':
                $max *= 1024;
        }

        return (int) $max;
    }

    public function getErrorMessage(): string
    {
        static $errors = [
            UPLOAD_ERR_INI_SIZE => 'The file "%s" exceeds your upload_max_filesize ini directive (limit is %d KiB).',
            UPLOAD_ERR_FORM_SIZE => 'The file "%s" exceeds the upload limit defined in your form.',
            UPLOAD_ERR_PARTIAL => 'The file "%s" was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_CANT_WRITE => 'The file "%s" could not be written on disk.',
            UPLOAD_ERR_NO_TMP_DIR => 'File could not be uploaded: missing temporary directory.',
            UPLOAD_ERR_EXTENSION => 'File upload was stopped by a PHP extension.',
        ];

        $errorCode = $this->getError();
        $maxFilesize = $errorCode === UPLOAD_ERR_INI_SIZE ? self::getMaxFilesize() / 1024 : 0;
        $message = isset($errors[$errorCode]) ? $errors[$errorCode] : 'The file "%s" was not uploaded due to an unknown error.';

        return sprintf($message, $this->getClientOriginalName(), $maxFilesize);
    }

    public function isEmpty(): bool
    {
        return $this->getError() === UPLOAD_ERR_NO_FILE;
    }

    public function hasError(): bool
    {
        return $this->getError() === null;
    }

    public function isValid(): bool
    {
        return is_uploaded_file($this->getpathName()) && $this->getError() === UPLOAD_ERR_OK;
    }

    private static function _extension(string $file): string
    {
        return pathinfo($file, PATHINFO_EXTENSION);
    }

    public static function _sanitize(string $string, string $replacement = '-'): string
    {
        $replace = preg_replace('/[^a-z0-9]/', $replacement, strtolower(trim(strip_tags($string), ' ')));

        $split = explode($replacement, $replace);

        foreach ($split as $key => $value) {
            if (empty($value)) {
                unset($split[$key]);
            }
        }

        return implode($replacement, $split);
    }

    public function validate(array $options): bool
    {
        $validator = $this->createValidator($options);
        try {
            return $validator->validate($this->file);
        } catch (RuntimeException $e) {
            $this->validationError = $e->getMessage();

            return false;
        }
    }

    public function validateWith(FileUploadValidator $validator): bool
    {
        try {
            return $validator->validate($this->file);
        } catch (RuntimeException $e) {
            $this->validationError = $e->getMessage();

            return false;
        }
    }

    public function moveSecurely(string $destination, array $options = [], bool $throw = true): string
    {
        $validator = $this->createValidator($options);
        $generateSafeName = $options['generateSafeName'] ?? true;

        try {
            return $validator->moveUploadedFile($this->file, $destination, $generateSafeName);
        } catch (RuntimeException $e) {
            if ($throw) {
                throw $e;
            }
            $this->validationError = $e->getMessage();

            return '';
        }
    }

    public function getValidationError(): ?string
    {
        return $this->validationError ?? null;
    }

    private function createValidator(array $options): FileUploadValidator
    {
        $type = $options['type'] ?? null;
        $maxSize = $options['maxSize'] ?? '5mb'; // Default to 5MB

        // Convert human-readable size to bytes (e.g., '2mb' -> 2097152)
        $maxSize = \Helpers\File\FileSizeHelper::toBytes($maxSize);

        if ($type === 'image') {
            return FileUploadValidator::forImages($maxSize);
        } elseif ($type === 'document') {
            return FileUploadValidator::forDocuments($maxSize);
        } elseif ($type === 'archive') {
            return FileUploadValidator::forArchives($maxSize);
        }

        return new FileUploadValidator(
            $options['mimeTypes'] ?? [],
            $options['extensions'] ?? [],
            $maxSize
        );
    }
}
