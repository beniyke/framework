<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * FileSizeHelper is a utility class for converting between human-readable file sizes and bytes.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File;

use InvalidArgumentException;

class FileSizeHelper
{
    /**
     * Convert human-readable file size to bytes.
     *
     * Supports formats like '2mb', '500kb', '1gb', etc.
     * Also accepts numeric values for backward compatibility.
     *
     * @param int|string $size File size (e.g., '2mb', '500kb', or 2097152)
     *
     * @return int Size in bytes
     *
     * @throws InvalidArgumentException If format is invalid
     */
    public static function toBytes(string|int $size): int
    {
        // If already numeric, return as-is (backward compatibility)
        if (is_int($size)) {
            if ($size < 0) {
                throw new InvalidArgumentException('File size cannot be negative');
            }

            return $size;
        }

        // Trim whitespace
        $size = trim($size);

        if (str_starts_with($size, '-')) {
            throw new InvalidArgumentException('File size cannot be negative');
        }

        // Extract number and unit
        if (! preg_match('/^(\d+(?:\.\d+)?)\s*([a-z]+)?$/i', $size, $matches)) {
            throw new InvalidArgumentException(
                "Invalid file size format: '{$size}'. Expected format: '2mb', '500kb', '1gb', etc."
            );
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2] ?? 'b');

        // Validate non-negative
        if ($number < 0) {
            throw new InvalidArgumentException('File size cannot be negative');
        }

        // Convert to bytes based on unit
        $bytes = match ($unit) {
            'b', 'bt', 'byte', 'bytes' => $number,
            'k', 'kb', 'kib', 'kilobyte', 'kilobytes' => $number * 1024,
            'm', 'mb', 'mib', 'megabyte', 'megabytes' => $number * 1048576,
            'g', 'gb', 'gib', 'gigabyte', 'gigabytes' => $number * 1073741824,
            't', 'tb', 'tib', 'terabyte', 'terabytes' => $number * 1099511627776,
            default => throw new InvalidArgumentException(
                "Unknown file size unit: '{$unit}'. Supported units: b, kb, mb, gb, tb"
            ),
        };

        return (int) $bytes;
    }

    /**
     * Convert bytes to human-readable file size.
     *
     * @param int $bytes     Size in bytes
     * @param int $precision Number of decimal places
     *
     * @return string Human-readable size (e.g., '2.00 MB')
     */
    public static function fromBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes < 0) {
            throw new InvalidArgumentException('Bytes cannot be negative');
        }

        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $size = $bytes / pow(1024, $power);

        return round($size, $precision) . ' ' . $units[$power];
    }
}
