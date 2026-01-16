<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Handles file-based encryption and decryption.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Encryption\Drivers;

use Exception;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use RuntimeException;

class FileEncryptor
{
    private const CIPHER_METHOD = 'aes-256-gcm';
    private const SALT_LENGTH = 16;
    private const TAG_LENGTH = 16;
    private const ITERATIONS = 65536;

    private string $password;

    private readonly FileReadWriteInterface $fileHandler;

    public function __construct(FileReadWriteInterface $fileHandler)
    {
        $this->fileHandler = $fileHandler;
    }

    public function password(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    private function deriveKey(string $salt): string
    {
        $key = hash_pbkdf2('sha256', $this->password, $salt, self::ITERATIONS, 32, true);
        if ($key === false) {
            throw new RuntimeException('Key derivation failed.');
        }

        return $key;
    }

    public function encrypt(string $source, string $destination): void
    {
        $contents = $this->fileHandler->get($source);

        $salt = random_bytes(self::SALT_LENGTH);
        $key = $this->deriveKey($salt);
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER_METHOD));
        $tag = '';

        $encrypted = openssl_encrypt($contents, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        if ($encrypted === false) {
            throw new RuntimeException('OpenSSL encryption failed.');
        }

        $payload = $salt . $iv . $tag . $encrypted;

        if (! $this->fileHandler->put($destination, $payload)) {
            throw new RuntimeException("Could not write encrypted file to: {$destination}");
        }
    }

    public function decrypt(string $file): string
    {
        $payload = $this->fileHandler->get($file);
        if ($payload === false) {
            throw new Exception("Could not read encrypted file: {$file}");
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);

        if (strlen($payload) < self::SALT_LENGTH + $iv_length + self::TAG_LENGTH) {
            throw new Exception('Encrypted file payload is corrupt or too short.');
        }

        $salt = substr($payload, 0, self::SALT_LENGTH);
        $iv = substr($payload, self::SALT_LENGTH, $iv_length);
        $tag = substr($payload, self::SALT_LENGTH + $iv_length, self::TAG_LENGTH);
        $encrypted = substr($payload, self::SALT_LENGTH + $iv_length + self::TAG_LENGTH);

        $key = $this->deriveKey($salt);
        $decrypted = openssl_decrypt($encrypted, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            throw new Exception('Decryption failed. The file is corrupt or password is incorrect.');
        }

        return $decrypted;
    }
}
