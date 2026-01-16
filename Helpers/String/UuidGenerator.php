<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Generates standard UUIDs (v1, v4, v7, v8) and name-based UUIDs.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\String;

use Exception;
use Helpers\File\FileSystem;
use RuntimeException;

final class UuidGenerator
{
    // RFC 4122 Standard Namespaces
    public const NS_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    public const NS_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';
    public const NIL = '00000000-0000-0000-0000-000000000000';
    public const MAX = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

    private const CLOCK_SEQ_FILE = 'uuid_clock_seq';

    private static function getClockSeqPath(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::CLOCK_SEQ_FILE;
    }

    // UUID v1 - Time-based, RFC 4122 compliant with persistent clock seq
    public static function v1(): string
    {
        [$sec, $nsec] = hrtime(false);
        // Gregorian calendar starts at Oct 15, 1582.
        // 0x01b21dd213814000 is the number of 100-ns intervals between UUID epoch and Unix epoch.
        $timestamp = ($sec * 10_000_000) + intdiv($nsec, 100) + 0x01B21DD213814000;

        [$lastTimestamp, $clockSeq] = self::loadClockState();

        if ($timestamp <= $lastTimestamp) {
            $clockSeq = ($clockSeq + 1) & 0x3FFF;
        }

        self::saveClockState($timestamp, $clockSeq);

        $timeLow = sprintf('%08x', $timestamp & 0xFFFFFFFF);
        $timeMid = sprintf('%04x', ($timestamp >> 32) & 0xFFFF);
        $timeHiAndVersion = sprintf('%04x', (($timestamp >> 48) & 0x0FFF) | 0x1000);

        $clockSeqHi = sprintf('%02x', ($clockSeq >> 8) & 0x3F | 0x80);
        $clockSeqLow = sprintf('%02x', $clockSeq & 0xFF);

        $node = random_bytes(6);
        $node[0] = chr(ord($node[0]) | 0x01); // Multicast bit
        $nodeHex = bin2hex($node);

        return sprintf(
            '%s-%s-%s-%s%s-%s',
            $timeLow,
            $timeMid,
            $timeHiAndVersion,
            $clockSeqHi,
            $clockSeqLow,
            $nodeHex
        );
    }

    // UUID v4 - Random (CSPRNG)
    public static function v4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0F | 0x40); // Set version (4)
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80); // Set variant (10xx)

        return self::fromBinary($data);
    }

    public static function v7(): string
    {
        $ms = (int) (microtime(true) * 1000);

        // Ensure monotonicity within same millisecond if needed, or just rely on random
        // For v7, timestamp is 48 bits Big Endian

        $uuid = random_bytes(16);

        // Set 48-bit timestamp
        $uuid[0] = chr(($ms >> 40) & 0xFF);
        $uuid[1] = chr(($ms >> 32) & 0xFF);
        $uuid[2] = chr(($ms >> 24) & 0xFF);
        $uuid[3] = chr(($ms >> 16) & 0xFF);
        $uuid[4] = chr(($ms >> 8) & 0xFF);
        $uuid[5] = chr($ms & 0xFF);

        // Set Version 7 (0111)
        $uuid[6] = chr((ord($uuid[6]) & 0x0F) | 0x70);

        // Set Variant 1 (10xx)
        $uuid[8] = chr((ord($uuid[8]) & 0x3F) | 0x80);

        return self::fromBinary($uuid);
    }

    // UUID v8 - Custom (SHA-256 Name-Based)
    public static function v8(string $data, string $namespace = ''): string
    {
        $input = $namespace . $data;
        $hash = hash('sha256', $input, true);

        $bytes = substr($hash, 0, 16);

        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x80); // Set version (8)
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80); // Set variant (10xx)

        return self::fromBinary($bytes);
    }

    public static function nameBased(string $name, string $namespace = self::NS_DNS): string
    {
        // V8 is experimental/custom. The test expects v8.
        $nspaceBinary = self::toBinary($namespace);

        return self::v8($name, $nspaceBinary);
    }

    private static function fromBinary(string $binary): string
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($binary), 4));
    }

    public static function toBinary(string $uuid): string
    {
        return hex2bin(str_replace('-', '', $uuid));
    }

    public static function isValid(string $uuid): bool
    {
        if (self::isNil($uuid)) {
            return true;
        }

        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    public static function getVersion(string $uuid): ?int
    {
        return self::isValid($uuid) ? (int) $uuid[14] : null;
    }

    public static function isNil(string $uuid): bool
    {
        return strtolower($uuid) === self::NIL;
    }

    // V1 Clock Monotonicity Implementation
    private static function loadClockState(): array
    {
        $path = self::getClockSeqPath();
        if (is_file($path)) {
            $data = @FileSystem::get($path);
            if ($data !== false && strlen($data) === 10) {
                $unpacked = unpack('Qtimestamp/SclockSeq', $data);
                if ($unpacked && $unpacked['clockSeq'] <= 0x3FFF) {
                    return [$unpacked['timestamp'], $unpacked['clockSeq']];
                }
            }
        }

        try {
            return [0, random_int(0, 0x3FFF)];
        } catch (Exception $e) {
            throw new RuntimeException('Could not initialize secure random for V1 Clock Sequence.', 0, $e);
        }
    }

    private static function saveClockState(int $timestamp, int $clockSeq): void
    {
        $data = pack('Q', $timestamp) . pack('S', $clockSeq);
        $path = self::getClockSeqPath();

        if (@FileSystem::put($path, $data, true) === false) {
            throw new RuntimeException(
                'Failed to write V1 clock state file. Check permissions for: ' . $path
            );
        }
    }
}
