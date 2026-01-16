<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Encryption service.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Encryption;

use BadMethodCallException;
use Helpers\Encryption\Drivers\FileEncryptor;
use Helpers\Encryption\Drivers\SymmetricEncryptorInterface;

class Encrypter
{
    private readonly SymmetricEncryptorInterface $stringDriver;

    private readonly FileEncryptor $fileDriver;

    private FileEncryptor|SymmetricEncryptorInterface $activeDriver;

    public function __construct(SymmetricEncryptorInterface $stringDriver, FileEncryptor $fileDriver)
    {
        $this->stringDriver = $stringDriver;
        $this->fileDriver = $fileDriver;
        $this->activeDriver = $this->stringDriver;
    }

    public function string(): self
    {
        $this->activeDriver = $this->stringDriver;

        return $this;
    }

    public function file(): self
    {
        $this->activeDriver = $this->fileDriver;

        return $this;
    }

    public function __call(string $method, array $arguments)
    {
        if (method_exists($this->activeDriver, $method)) {
            return $this->activeDriver->$method(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist on the current encryption driver.', get_class($this->activeDriver), $method));
    }
}
