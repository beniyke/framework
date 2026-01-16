<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for symmetric encryption drivers.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Encryption\Drivers;

interface SymmetricEncryptorInterface
{
    public function encrypt(string $data): string;

    public function decrypt(string $payload): string;

    public function hashPassword(string $password): string;

    public function verifyPassword(string $password, string $hash): bool;
}
