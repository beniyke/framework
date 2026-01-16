<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The Benchmark class provides utilities for performance profiling.
 * It allows measuring execution time and memory usage for code blocks or specific operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers;

use RuntimeException;

class Benchmark
{
    /**
     * @var array<string, mixed>
     */
    protected static array $timers = [];

    public static function start(string $key): void
    {
        static::$timers[$key] = [
            'time' => microtime(true),
            'memory' => memory_get_usage(),
        ];
    }

    public static function has(string $key): bool
    {
        return isset(static::$timers[$key]);
    }

    /**
     * Stop a timer and return the duration in milliseconds.
     */
    public static function stop(string $key): float
    {
        if (! isset(static::$timers[$key])) {
            throw new RuntimeException("Timer '{$key}' has not been started.");
        }

        $stopTime = microtime(true);
        $stopMemory = memory_get_usage();

        $startData = static::$timers[$key];

        // Calculate duration in milliseconds
        $duration = ($stopTime - $startData['time']) * 1000;
        $memory = $stopMemory - $startData['memory'];

        static::$timers[$key . '_duration'] = $duration;
        static::$timers[$key . '_memory'] = $memory;

        unset(static::$timers[$key]);

        return $duration;
    }

    /**
     * Get the duration of a specific timer in milliseconds.
     */
    public static function get(string $key): ?float
    {
        return static::$timers[$key . '_duration'] ?? null;
    }

    public static function memory(string $key): ?int
    {
        return static::$timers[$key . '_memory'] ?? null;
    }

    /**
     * Get all recorded timers.
     *
     * @return array<string, array{time: float, memory: int}>
     */
    public static function getAll(): array
    {
        $results = [];
        foreach (static::$timers as $key => $value) {
            if (str_ends_with($key, '_duration')) {
                $originalKey = substr($key, 0, -9);
                $results[$originalKey] = [
                    'time' => $value,
                    'memory' => static::$timers[$originalKey . '_memory'] ?? 0,
                ];
            }
        }

        return $results;
    }

    /**
     * Measure the execution time of a callback.
     */
    public static function measure(string $key, callable $callback): mixed
    {
        static::start($key);

        try {
            return $callback();
        } finally {
            static::stop($key);
        }
    }

    public static function reset(): void
    {
        static::$timers = [];
    }
}
