<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Driver for symmetric encryption operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Encryption\Drivers;

use InvalidArgumentException;
use RuntimeException;

class SymmetricEncryptor implements SymmetricEncryptorInterface
{
    private const CIPHER_METHOD = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    private readonly string $key;

    /** @var array<string> */
    private readonly array $previousKeys;

    public function __construct(string $key, array $previousKeys = [])
    {
        $this->key = $this->parseKey($key);

        if (strlen($this->key) !== 32) {
            throw new InvalidArgumentException('Encryption key must be a 32-byte (256-bit) secret.');
        }

        $this->previousKeys = array_map(function ($key) {
            return $this->parseKey($key);
        }, $previousKeys);
    }

    private function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            $key = substr($key, 7);
        }

        $decoded = base64_decode($key, true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid encryption key format.');
        }

        return $decoded;
    }

    public function encrypt(string $data): string
    {
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        if ($iv_length === false) {
            throw new RuntimeException('Invalid cipher method.');
        }

        $iv = openssl_random_pseudo_bytes($iv_length);
        $tag = '';

        $encrypted = openssl_encrypt($data, self::CIPHER_METHOD, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed.');
        }

        $payload = $iv . $tag . $encrypted;

        return base64_encode($payload);
    }

    public function decrypt(string $payload): string
    {
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $decoded = base64_decode($payload, true);

        if ($decoded === false || strlen($decoded) < $iv_length + self::TAG_LENGTH) {
            throw new InvalidArgumentException('Payload is invalid or corrupted.');
        }

        $iv = substr($decoded, 0, $iv_length);
        $tag = substr($decoded, $iv_length, self::TAG_LENGTH);
        $encrypted = substr($decoded, $iv_length + self::TAG_LENGTH);

        // Try primary key
        $result = openssl_decrypt($encrypted, self::CIPHER_METHOD, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($result !== false) {
            return $result;
        }

        // Try previous keys
        foreach ($this->previousKeys as $paramKey) {
            $result = openssl_decrypt($encrypted, self::CIPHER_METHOD, $paramKey, OPENSSL_RAW_DATA, $iv, $tag);
            if ($result !== false) {
                return $result;
            }
        }

        throw new RuntimeException('Decryption failed or data is corrupt/tampered.');
    }

    public function hashPassword(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        if ($hash === false) {
            throw new RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
