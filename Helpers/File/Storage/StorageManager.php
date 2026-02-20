<?php

declare(strict_types=1);

namespace Helpers\File\Storage;

use Closure;
use Core\Services\ConfigServiceInterface;
use Helpers\File\Storage\Adapters\AzureBlobAdapter;
use Helpers\File\Storage\Adapters\FtpAdapter;
use Helpers\File\Storage\Adapters\GoogleCloudStorageAdapter;
use Helpers\File\Storage\Adapters\LocalAdapter;
use Helpers\File\Storage\Adapters\MemoryAdapter;
use Helpers\File\Storage\Adapters\NullAdapter;
use Helpers\File\Storage\Adapters\S3Adapter;
use Helpers\File\Storage\Adapters\SftpAdapter;
use Helpers\File\Storage\Adapters\WebDavAdapter;
use Helpers\File\Storage\Adapters\ZipAdapter;
use InvalidArgumentException;

class StorageManager
{
    /**
     * The resolved storage disks.
     */
    protected array $disks = [];

    /**
     * The registered custom drivers.
     */
    protected array $customDrivers = [];

    public function __construct(
        protected readonly ConfigServiceInterface $config
    ) {
    }

    public function disk(?string $name = null): StorageInterface
    {
        $name = $name ?: $this->getDefaultDisk();

        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        return $this->disks[$name] = $this->resolve($name);
    }

    protected function resolve(string $name): StorageInterface
    {
        $config = $this->config->get("filesystems.disks.{$name}");

        if (is_null($config)) {
            throw new InvalidArgumentException("Disk [{$name}] is not defined.");
        }

        if (isset($this->customDrivers[$config['driver']])) {
            return $this->callCustomDriver($name, $config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    protected function createLocalDriver(array $config): LocalAdapter
    {
        return new LocalAdapter($config);
    }

    protected function createS3Driver(array $config): S3Adapter
    {
        return new S3Adapter($config);
    }

    protected function createMemoryDriver(array $config): MemoryAdapter
    {
        return new MemoryAdapter($config);
    }

    protected function createFtpDriver(array $config): FtpAdapter
    {
        return new FtpAdapter($config);
    }

    protected function createSftpDriver(array $config): SftpAdapter
    {
        return new SftpAdapter($config);
    }

    protected function createZipDriver(array $config): ZipAdapter
    {
        return new ZipAdapter($config);
    }

    protected function createWebdavDriver(array $config): WebDavAdapter
    {
        return new WebDavAdapter($config);
    }

    /**
     * Create an instance of the Azure Blob storage driver.
     */
    protected function createAzureDriver(array $config): AzureBlobAdapter
    {
        return new AzureBlobAdapter($config);
    }

    /**
     * Create an instance of the Google Cloud Storage driver.
     */
    protected function createGcsDriver(array $config): GoogleCloudStorageAdapter
    {
        return new GoogleCloudStorageAdapter($config);
    }

    protected function createNullDriver(array $config): NullAdapter
    {
        return new NullAdapter($config);
    }

    /**
     * Call a custom driver creator.
     */
    protected function callCustomDriver(string $name, array $config): StorageInterface
    {
        return $this->customDrivers[$config['driver']]($this->config, $config);
    }

    /**
     * Register a custom driver creator Closure.
     */
    public function extend(string $driver, Closure $callback): self
    {
        $this->customDrivers[$driver] = $callback;

        return $this;
    }

    public function getDefaultDisk(): string
    {
        return $this->config->get('filesystems.default', 'local');
    }

    /**
     * Dynamically call the default disk instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->disk()->{$method}(...$parameters);
    }
}
