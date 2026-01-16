<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Dotenv loader for managing environment variables.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Services;

use Core\Support\Adapters\Interfaces\EnvironmentInterface;
use Exception;
use Helpers\Encryption\Drivers\FileEncryptor;
use Helpers\File\Adapters\FileReadWriteAdapter;
use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use InvalidArgumentException;
use RuntimeException;

class Dotenv implements DotenvInterface
{
    private readonly string $directory;

    private readonly string $cachePath;

    private array $envData = [];

    private bool $loaded = false;

    private readonly EnvironmentInterface $environment;

    private readonly PathResolverInterface $paths;

    private readonly FileMetaInterface $fileMeta;

    private readonly FileReadWriteInterface $fileReadWrite;

    private readonly FileManipulationInterface $fileManipulation;

    public function __construct(string $directory, EnvironmentInterface $environment, PathResolverInterface $paths, FileMetaInterface $fileMeta, FileReadWriteInterface $fileReadWrite, FileManipulationInterface $fileManipulation)
    {
        if (! $fileMeta->exists($directory) || ! $fileMeta->isDir($directory)) {
            throw new InvalidArgumentException("Dotenv directory does not exist or is not a directory: {$directory}");
        }

        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);

        $this->environment = $environment;
        $this->paths = $paths;
        $this->fileMeta = $fileMeta;
        $this->fileReadWrite = $fileReadWrite;
        $this->fileManipulation = $fileManipulation;
        $this->cachePath = $this->paths->cachePath('prod' . DIRECTORY_SEPARATOR . '.env.cache');

        $this->setup();
    }

    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        if ($this->environment->isProduction() && $this->fileMeta->exists($this->cachePath)) {
            $this->envData = require $this->cachePath;
            $this->applyEnv();
            $this->loaded = true;

            return;
        }

        $this->parseFile();
        $this->applyEnv();
        $this->loaded = true;

        if ($this->environment->isProduction()) {
            $this->cache();
        }
    }

    private function parseFile(): void
    {
        $filePath = $this->directory . DIRECTORY_SEPARATOR . '.env';
        $encryptedPath = $filePath . '.encrypted';

        $content = null;

        if ($this->fileMeta->exists($filePath) && $this->fileMeta->isReadable($filePath)) {
            $content = $this->fileReadWrite->get($filePath);
        } elseif ($this->fileMeta->exists($encryptedPath) && $this->fileMeta->isReadable($encryptedPath)) {
            $key = $_ENV['ANCHOR_ENV_ENCRYPTION_KEY'] ?? $_ENV['APP_ENV_ENCRYPTION_KEY'] ?? getenv('ANCHOR_ENV_ENCRYPTION_KEY') ?? getenv('APP_ENV_ENCRYPTION_KEY');

            if ($key) {
                if (str_starts_with($key, 'base64:')) {
                    $key = base64_decode(substr($key, 7));
                }

                try {
                    $encryptor = new FileEncryptor(new FileReadWriteAdapter());
                    $encryptor->password($key);
                    $content = $encryptor->decrypt($encryptedPath);
                } catch (Exception) {
                    // Fail silently or log? Framework convention usually fails silently for Dotenv if file missing,
                    // but if encrypted file exists and decryption fails, it might be worth knowing.
                }
            }
        }

        if ($content === null) {
            return;
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
        $this->envData = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2) + [1 => null];
            $key = trim($key);
            $value = trim($value ?? '');

            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1);
            }

            $this->envData[$key] = $value;
        }
    }

    private function applyEnv(): void
    {
        foreach ($this->envData as $key => $value) {
            if (! isset($_ENV[$key]) && ! isset($_SERVER[$key])) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    public function cache(): void
    {
        $cacheDir = dirname($this->cachePath);

        if (! $this->fileManipulation->mkdir($cacheDir, 0777, true)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $cacheDir));
        }

        if (! $this->loaded) {
            $this->parseFile();
        }

        $cacheContent = '<?php return ' . var_export($this->envData, true) . ';';

        if ($this->fileReadWrite->put($this->cachePath, $cacheContent) === false) {
            throw new RuntimeException("Unable to write to cache file: {$this->cachePath}");
        }
    }

    protected function setup(): void
    {
        $envPath = $this->directory . DIRECTORY_SEPARATOR . '.env';
        $examplePath = $this->directory . DIRECTORY_SEPARATOR . '.env.example';

        $encryptedPath = $envPath . '.encrypted';

        if (! $this->fileMeta->exists($envPath) && ! $this->fileMeta->exists($encryptedPath)) {
            if (! $this->fileMeta->exists($examplePath)) {
                throw new RuntimeException('Missing example env file: .env.example');
            }

            $lines = explode("\n", $this->fileReadWrite->get($examplePath));
            $output_lines = $this->generateAppKey($lines);

            if ($this->fileReadWrite->put($envPath, implode("\n", $output_lines) . "\n") === false) {
                throw new RuntimeException('Unable to write to file: ' . $envPath);
            }
        }
    }

    private function generatekey(): string
    {
        $raw_key = random_bytes(32);
        $key = base64_encode($raw_key);

        return $key;
    }

    private function generateAppKey(array $lines): array
    {
        $output_lines = [];
        $hasAppKey = false;

        $appKeyGenerated = $this->generateKey();

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (str_starts_with($trimmedLine, 'APP_KEY=')) {
                $hasAppKey = true;
                [$key, $value] = explode('=', $line, 2);
                $value = trim($value, " \t\n\r\0\x0B\"'");

                if (empty($value) || in_array($value, ['null', '(null)'])) {
                    $output_lines[] = "APP_KEY={$appKeyGenerated}";
                } else {
                    $output_lines[] = $line;
                }
            } else {
                $output_lines[] = $line;
            }
        }

        if (! $hasAppKey) {
            array_unshift($output_lines, "APP_KEY={$appKeyGenerated}");
        }

        return $output_lines;
    }

    public function getValue(string $key, mixed $default = null): mixed
    {
        if (! $this->loaded) {
            $this->load();
        }
        $value = $this->envData[$key] ?? $default;

        return $this->castValue($value);
    }

    private function castValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }

    public function setValue(string $key, mixed $value): void
    {
        if (! $this->loaded) {
            $this->parseFile();
        }

        $this->envData[$key] = $value;
        $this->writeToEnvFile();

        $this->applyEnv();
    }

    public function generateAndSaveAppKey(): void
    {
        if (! $this->loaded) {
            $this->parseFile();
        }

        $newKey = $this->generateKey();

        $this->envData['APP_KEY'] = $newKey;

        $this->writeToEnvFile();
        $this->applyEnv();
    }

    private function writeToEnvFile(): void
    {
        $filePath = $this->directory . DIRECTORY_SEPARATOR . '.env';
        $content = $this->fileReadWrite->get($filePath);
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));

        $output_lines = [];
        $existingKeys = [];

        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                [$lineKey] = explode('=', $line, 2);
                $lineKey = trim($lineKey);

                if (array_key_exists($lineKey, $this->envData)) {
                    $output_lines[] = "{$lineKey}=" . $this->formatValue($this->envData[$lineKey]);
                    $existingKeys[] = $lineKey;
                } else {
                    $output_lines[] = $line;
                }
            } else {
                $output_lines[] = $line;
            }
        }

        foreach ($this->envData as $key => $value) {
            if (! in_array($key, $existingKeys)) {
                $output_lines[] = "\n{$key}=" . $this->formatValue($value);
            }
        }

        if ($this->fileReadWrite->put($filePath, implode("\n", $output_lines) . "\n") === false) {
            throw new RuntimeException('Unable to write to file: ' . $filePath);
        }

        if ($this->fileMeta->exists($this->cachePath)) {
            $this->fileManipulation->delete($this->cachePath);
        }
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            $value = 'null';
        } else {
            $value = (string) $value;
        }

        if (preg_match('/\s|"\'#=;/', $value)) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return $value;
    }
}
