<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Provides a convenient way to manage file-based cache.
 *
 * It implements CacheInterface (PSR-16 inspired) and ensures the cache directory
 * remains within the Paths::storagePath() root, while allowing fluent sub-pathing.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File;

use Core\Services\ConfigServiceInterface;
use Exception;
use Helpers\File\Contracts\CacheInterface;
use RuntimeException;

final class Cache implements CacheInterface
{
    protected string $basePath;

    protected string $subPath = '';

    protected string $prefix;

    protected string $extension;

    protected int $maxItems = 10000;

    protected array $metrics = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
    ];

    protected array $tags = [];

    protected ?ConfigServiceInterface $config = null;

    private function __construct(string $absoluteBasePath, string $prefix = '', string $extension = 'cache', ?ConfigServiceInterface $config = null)
    {
        $this->basePath = rtrim($absoluteBasePath, '/');
        $this->prefix = trim($prefix, '.') === '' ? '' : trim($prefix, '.') . '.';
        $this->extension = trim($extension, '.') === '' ? '' : '.' . trim($extension, '.');
        $this->config = $config;

        if (! FileSystem::mkdir($this->basePath, 0755, true)) {
            throw new RuntimeException("Base cache directory ('{$this->basePath}') could not be created or is not writable.");
        }

        if (! is_readable($this->basePath) || ! is_writable($this->basePath)) {
            throw new RuntimeException(vsprintf("%s(): Base Cache directory ('%s') is not readable or writable.", [__METHOD__, $this->basePath]));
        }
    }

    public static function create(string $path = 'cache', string $prefix = '', string $extension = 'cache'): self
    {
        $absolutePath = Paths::storagePath($path);
        $config = resolve(ConfigServiceInterface::class);

        return new self($absolutePath, $prefix, $extension, $config);
    }

    public function withPath(string $subPath): self
    {
        $instance = clone $this;
        $instance->subPath = trim($subPath, DIRECTORY_SEPARATOR . '/');
        $fullSubPath = $this->getCachePath($instance->subPath);

        if (! FileSystem::mkdir($fullSubPath, 0755, true)) {
            throw new RuntimeException("Cache sub-directory ('{$fullSubPath}') could not be created or is not writable.");
        }

        return $instance;
    }

    public function tags(array $tags): self
    {
        $instance = clone $this;
        $instance->tags = $tags;

        return $instance;
    }

    public function flushTags(array $tags): bool
    {
        $tagDir = $this->basePath . '/_tags';
        if (! FileSystem::mkdir($tagDir, 0755, true)) {
            return false;
        }

        foreach ($tags as $tag) {
            FileSystem::replace($tagDir . '/' . md5($tag), (string) time());
        }

        return true;
    }

    protected function getTagTimestamp(string $tag): int
    {
        $file = $this->basePath . '/_tags/' . md5($tag);
        if (FileSystem::exists($file)) {
            return (int) FileSystem::get($file);
        }

        return 0;
    }

    public function write(string $key, mixed $value, int $ttl = 0): bool
    {
        $ttl = ((int) $ttl <= 0) ? 0 : ((int) $ttl + time());

        $payload = [
            '__payload_type' => 'tagged',
            'data' => $value,
            'tags' => $this->tags,
            'created_at' => time(),
        ];

        $data = "{$ttl}\n" . serialize($payload);

        $this->metrics['writes']++;
        $this->enforceLimit();

        return FileSystem::replace($this->cacheFile($key), $data);
    }

    public function read(string $key, mixed $default = null): mixed
    {
        $filename = $this->cacheFile($key);
        if (! FileSystem::exists($filename)) {
            $this->metrics['misses']++;

            return $default;
        }

        try {
            $content = FileSystem::get($filename, $lock = true);
        } catch (Exception $e) {
            $this->metrics['misses']++;

            return $default;
        }

        if (empty($content)) {
            FileSystem::delete($filename);
            $this->metrics['misses']++;

            return $default;
        }

        $parts = explode("\n", $content, 2);

        if (count($parts) !== 2) {
            FileSystem::delete($filename);
            $this->metrics['misses']++;

            return $default;
        }

        $expire = (int) $parts[0];
        $cache = $parts[1];

        if ($expire === 0 || time() < $expire) {
            $allowedClasses = $this->config?->get('cache.allowed_classes', []) ?? [];
            $value = @unserialize($cache, ['allowed_classes' => $allowedClasses]);

            if (is_array($value) && isset($value['__payload_type']) && $value['__payload_type'] === 'tagged') {
                foreach ($value['tags'] as $tag) {
                    if ($this->getTagTimestamp($tag) > $value['created_at']) {
                        FileSystem::delete($filename);
                        $this->metrics['misses']++;

                        return $default;
                    }
                }

                $value = $value['data'];
            }

            $this->metrics['hits']++;

            return ($value === false && $cache !== 'b:0;') ? $default : $value;
        }

        FileSystem::delete($filename);
        $this->metrics['misses']++;

        return $default;
    }

    public function has(string $key): bool
    {
        $filename = $this->cacheFile($key);
        if (! FileSystem::exists($filename)) {
            return false;
        }

        try {
            $handle = fopen($filename, 'r');
            if (! $handle) {
                return false;
            }

            if (flock($handle, LOCK_SH)) {
                $expire = (int) trim(fgets($handle));
                flock($handle, LOCK_UN);
            } else {
                $expire = 0;
            }

            fclose($handle);
        } catch (Exception $e) {
            return false;
        }

        return $expire === 0 || time() < $expire;
    }

    public function delete(string $key): bool
    {
        $deleted = FileSystem::delete($this->cacheFile($key));
        if ($deleted) {
            $this->metrics['deletes']++;
        }

        return $deleted;
    }

    public function clear(): bool
    {
        $pattern = $this->cacheFile('*');
        $success = true;

        foreach (glob($pattern) as $file) {
            if (! is_dir($file)) {
                if (! FileSystem::delete($file)) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    public function remember(string $key, int $seconds, callable $callback): mixed
    {
        $value = $this->read($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();

        if ($value !== null) {
            $this->write($key, $value, $seconds);
        }

        return $value;
    }

    public function permanent(string $key, callable $callback): mixed
    {
        $value = $this->read($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();

        if ($value !== null) {
            $this->write($key, $value, 0);
        }

        return $value;
    }

    private function getCachePath(string $subPath = ''): string
    {
        $subPath = $subPath ?: $this->subPath;

        if ($subPath) {
            return $this->basePath . DIRECTORY_SEPARATOR . $subPath;
        }

        return $this->basePath;
    }

    private function cacheFile(string $key): string
    {
        $directory = $this->getCachePath();

        return $directory . DIRECTORY_SEPARATOR . $this->prefix . $key . $this->extension;
    }

    /**
     * Acquire a lock to prevent cache stampede
     */
    public function acquireLock(string $key, int $timeout = 10): bool
    {
        $lockFile = $this->cacheFile("lock.{$key}");
        $expire = time() + $timeout;

        if (FileSystem::exists($lockFile)) {
            try {
                $lockExpire = (int) FileSystem::get($lockFile);
                if ($lockExpire > time()) {
                    return false;
                }
            } catch (Exception $e) {
                // Proceed if Lock file is corrupted
            }
        }

        return FileSystem::replace($lockFile, (string) $expire);
    }

    public function releaseLock(string $key): bool
    {
        return $this->delete("lock.{$key}");
    }

    /**
     * Remember with stale while revalidating
     */
    public function rememberWithStale(string $key, int $ttl, callable $callback): mixed
    {
        $data = $this->readRaw($key);

        if ($data && isset($data['soft_expire'], $data['hard_expire'])) {
            $now = time();
            if ($now < $data['soft_expire']) {
                $this->metrics['hits']++;

                return $data['value'];
            }

            if ($now < $data['hard_expire']) {
                $staleValue = $data['value'];

                if (function_exists('defer')) {
                    defer(function () use ($key, $ttl, $callback) {
                        if ($this->acquireLock($key, 30)) {
                            try {
                                $fresh = $callback();
                                $this->writeWithExpiry($key, $fresh, $ttl);
                            } finally {
                                $this->releaseLock($key);
                            }
                        }
                    });
                }

                return $staleValue;
            }
        }

        $this->metrics['misses']++;

        if ($this->acquireLock($key, 30)) {
            try {
                $value = $callback();
                $this->writeWithExpiry($key, $value, $ttl);

                return $value;
            } finally {
                $this->releaseLock($key);
            }
        }

        usleep(100000); // 100ms
        $cached = $this->read($key);

        return $cached !== null ? $cached : $callback();
    }

    /**
     * Read raw cache data without expiration check
     */
    protected function readRaw(string $key): ?array
    {
        $filename = $this->cacheFile($key);
        if (! FileSystem::exists($filename)) {
            return null;
        }

        try {
            $content = FileSystem::get($filename, true);
        } catch (Exception $e) {
            return null;
        }

        if (empty($content)) {
            return null;
        }

        $parts = explode("\n", $content, 2);

        if (count($parts) !== 2) {
            return null;
        }

        $expire = (int) $parts[0];
        $cache = $parts[1];
        $allowedClasses = $this->config?->get('cache.allowed_classes', []) ?? [];
        $value = @unserialize($cache, ['allowed_classes' => $allowedClasses]);

        if (is_array($value) && isset($value['__payload_type']) && $value['__payload_type'] === 'tagged') {
            foreach ($value['tags'] as $tag) {
                if ($this->getTagTimestamp($tag) > $value['created_at']) {
                    return null;
                }
            }
            $value = $value['data'];
        }

        if ($value === false && $cache !== 'b:0;') {
            return null;
        }

        if (is_array($value) && isset($value['__stale_wrapper'])) {
            return $value;
        }

        return [
            'value' => $value,
            'hard_expire' => $expire,
            'soft_expire' => $expire > 0 ? (int) ($expire - (($expire - time()) * 0.2)) : 0,
        ];
    }

    protected function writeWithExpiry(string $key, mixed $value, int $ttl): bool
    {
        $jitteredTtl = $this->addJitter($ttl);
        $now = time();

        $data = [
            '__stale_wrapper' => true,
            'value' => $value,
            'soft_expire' => $now + (int) ($jitteredTtl * 0.8),
            'hard_expire' => $now + $jitteredTtl,
        ];

        return $this->write($key, $data, $jitteredTtl);
    }

    protected function addJitter(int $ttl): int
    {
        $jitterPercent = 0.1; // ±10%
        $jitter = rand((int) (-$ttl * $jitterPercent), (int) ($ttl * $jitterPercent));

        return max(1, $ttl + $jitter);
    }

    /**
     * Enforce cache size limits with LRU eviction
     */
    protected function enforceLimit(): void
    {
        $pattern = $this->getCachePath() . DIRECTORY_SEPARATOR . '*' . $this->extension;
        $files = glob($pattern);

        if (! $files || count($files) < $this->maxItems) {
            return;
        }

        // Sort by access time (LRU)
        usort($files, fn ($a, $b) => fileatime($a) <=> fileatime($b));

        // Remove oldest 10%
        $toRemove = max(1, (int) (count($files) * 0.1));
        foreach (array_slice($files, 0, $toRemove) as $file) {
            @unlink($file);
        }
    }

    public function getMetrics(): array
    {
        $total = $this->metrics['hits'] + $this->metrics['misses'];
        $hitRate = $total > 0 ? round(($this->metrics['hits'] / $total) * 100, 2) : 0;

        return array_merge($this->metrics, [
            'hit_rate' => $hitRate . '%',
            'hit_rate_numeric' => $hitRate,
            'total_requests' => $total,
            'cache_size' => $this->getCacheSize(),
        ]);
    }

    protected function getCacheSize(): int
    {
        $pattern = $this->getCachePath() . DIRECTORY_SEPARATOR . '*' . $this->extension;
        $files = glob($pattern);

        return $files ? count($files) : 0;
    }

    public function resetMetrics(): void
    {
        $this->metrics = ['hits' => 0, 'misses' => 0, 'writes' => 0, 'deletes' => 0];
    }

    public function setMaxItems(int $maxItems): self
    {
        $this->maxItems = max(1, $maxItems);

        return $this;
    }

    public function keys(): array
    {
        $pattern = $this->getCachePath() . DIRECTORY_SEPARATOR . '*' . $this->extension;
        $files = glob($pattern);
        $keys = [];

        if ($files) {
            foreach ($files as $file) {
                if (! is_dir($file)) {
                    $filename = basename($file, $this->extension);
                    // Remove prefix if present
                    if ($this->prefix !== '' && strpos($filename, $this->prefix) === 0) {
                        $filename = substr($filename, strlen($this->prefix));
                    }
                    $keys[] = $filename;
                }
            }
        }

        return $keys;
    }
}
