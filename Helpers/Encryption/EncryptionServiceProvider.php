<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Service provider for encryption services.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Encryption;

use Core\Ioc\ContainerInterface;
use Core\Services\ConfigServiceInterface;
use Core\Services\ServiceProvider;
use Helpers\Encryption\Drivers\FileEncryptor;
use Helpers\Encryption\Drivers\SymmetricEncryptor;
use Helpers\Encryption\Drivers\SymmetricEncryptorInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use RuntimeException;

class EncryptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(SymmetricEncryptorInterface::class, function (ContainerInterface $container) {
            $config = $container->get(ConfigServiceInterface::class);
            $key = $config->get('encryption_key');
            $previousKeys = $config->get('previous_keys', '');

            if (empty($key)) {
                throw new RuntimeException('Encryption key is missing. Please set APP_KEY in your .env file.');
            }

            $previousKeysArray = array_filter(array_map('trim', explode(',', (string) $previousKeys)));

            return new SymmetricEncryptor($key, $previousKeysArray);
        });

        $this->container->singleton(FileEncryptor::class, function (ContainerInterface $container) {
            return new FileEncryptor($container->get(FileReadWriteInterface::class));
        });

        $this->container->singleton(Encrypter::class, function (ContainerInterface $container) {
            return new Encrypter(
                $container->get(SymmetricEncryptorInterface::class),
                $container->get(FileEncryptor::class)
            );
        });
    }
}
