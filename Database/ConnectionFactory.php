<?php

declare(strict_types=1);

namespace Database;

/**
 * Anchor Framework
 *
 * ConnectionFactory is responsible for creating database connection instances
 * based on the provided configuration. It handles driver-specific setup and initialization commands.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use DateTime;
use DateTimeZone;
use Exception;

class ConnectionFactory
{
    public static function create(ConnectionConfigInterface $dbConnect): Connection
    {
        $driver = $dbConnect->getDriver();
        $connection = null;

        if ($driver === 'sqlite') {
            $connection = Connection::configure($dbConnect->getDsn())
                ->persistent($dbConnect->isPersistent())
                ->connect();

            $sqliteConfig = $dbConnect->getConfigArray();
            $journalMode = $sqliteConfig['journal_mode'] ?? 'DELETE';
            $busyTimeout = $sqliteConfig['busy_timeout'] ?? 5000;
            $synchronous = $sqliteConfig['synchronous'] ?? 'FULL';

            $connection->initCommand("PRAGMA journal_mode = {$journalMode}")
                ->initCommand("PRAGMA busy_timeout = {$busyTimeout}")
                ->initCommand("PRAGMA synchronous = {$synchronous}")
                ->initCommand('PRAGMA foreign_keys = ON');
        } else {
            $timezoneOffset = self::get_utc_offset_from_timezone($dbConnect->getTimezone());

            $connection = Connection::configure($dbConnect->getDsn(), $dbConnect->getUser(), $dbConnect->getPassword())
                ->options($dbConnect->getOptions())
                ->persistent($dbConnect->isPersistent())
                ->connect()
                ->initCommand("SET time_zone = '" . $timezoneOffset . "'")
                ->initCommand('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci')
                ->initCommand("SET SESSION sql_mode = 'STRICT_TRANS_TABLES'");
        }

        return $connection;
    }

    private static function get_utc_offset_from_timezone(string $timezoneName): string
    {
        try {
            $tz = new DateTimeZone($timezoneName);
            $now = new DateTime('now', $tz);

            return $now->format('P');
        } catch (Exception $e) {
            return '+00:00';
        }
    }
}
