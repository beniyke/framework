<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class extends Carbon and provides application-specific helper methods
 * for handling date and time logic, designed for production environments.
 * It prioritizes safety, readability, and maintenance.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 *
 * @method static DateTimeHelper|static parse($time = null, $tz = null)
 * @method        DateTimeHelper|static addMinutes(int $value = 1)
 * @method        DateTimeHelper|static addHours(int $value = 1)
 * @method        DateTimeHelper|static addDays(int $value = 1)
 * @method        DateTimeHelper|static addWeeks(int $value = 1)
 * @method        DateTimeHelper|static addMonths(int $value = 1)
 * @method        DateTimeHelper|static addYears(int $value = 1)
 * @method        DateTimeHelper|static subMinutes(int $value = 1)
 * @method        DateTimeHelper|static subHours(int $value = 1)
 * @method        DateTimeHelper|static subDays(int $value = 1)
 * @method        DateTimeHelper|static subWeeks(int $value = 1)
 * @method        DateTimeHelper|static subMonths(int $value = 1)
 * @method        DateTimeHelper|static subYears(int $value = 1)
 * @method        DateTimeHelper|static setTime(int $hour, int $minute, int $second = 0, int $microseconds = 0)
 * @method        DateTimeHelper|static setHour(int $value)
 * @method        DateTimeHelper|static setMinute(int $value)
 * @method        DateTimeHelper|static setSecond(int $value)
 * @method        DateTimeHelper|static startOfDay()
 * @method        DateTimeHelper|static endOfDay()
 * @method        DateTimeHelper|static startOfWeek()
 * @method        DateTimeHelper|static endOfWeek()
 * @method        DateTimeHelper|static startOfMonth()
 * @method        DateTimeHelper|static endOfMonth()
 * @method        DateTimeHelper|static startOfYear()
 * @method        DateTimeHelper|static endOfYear()
 * @method        DateTimeHelper|static setTimezone($value)
 */

namespace Helpers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

class DateTimeHelper extends CarbonImmutable
{
    /**
     * Get a DateTimeHelper instance for the current time.
     */
    public static function now($tz = null): static
    {
        return static::instance(CarbonImmutable::now($tz));
    }

    /**
     * Set the current time for testing.
     * Synchronizes across Carbon and CarbonImmutable to ensure consistency.
     */
    public static function setTestNow($testNow = null): void
    {
        Carbon::setTestNow($testNow);
        CarbonImmutable::setTestNow($testNow);
    }

    /**
     * Alias for now() to return a mutable instance if specifically needed.
     */
    public static function nowMutable($tz = null): Carbon
    {
        return Carbon::instance($tz instanceof DateTimeZone ? parent::now($tz) : parent::now());
    }

    /**
     * Create an immutable instance from a string or DateTime object.
     */
    public static function immutable($date = null, $tz = null): static
    {
        return static::parse($date, $tz);
    }

    /**
     * Create a mutable instance from a string or DateTime object.
     */
    public static function mutable($date = null, $tz = null): Carbon
    {
        return Carbon::parse($date, $tz);
    }

    /**
     * Standard format for storing datetimes in a database (UTC recommended).
     */
    protected const DB_FORMAT = 'Y-m-d H:i:s';

    /**
     * Standard format for user-friendly short date output (e.g., 'Wed, Oct 31 2025').
     */
    protected const SHORT_DATE_FORMAT = 'D, M d Y';

    /**
     * Standard format for user-friendly datetime output (e.g., 'Oct 31, 2025 at 05:14 PM').
     */
    protected const FRIENDLY_DATETIME_FORMAT = 'M d, Y \a\t h:i A';

    /**
     * A standardized static alias for creating a Carbon instance.
     */
    public static function createFrom(string $date): static
    {
        return static::parse($date);
    }

    /**
     * Safely parses a date string, returning an instance or null if the input is null/empty or invalid.
     */
    public static function safeParse(?string $date): ?static
    {
        if (empty($date)) {
            return null;
        }
        try {
            return static::parse($date);
        } catch (InvalidArgumentException $e) {
            logger('error.log')->warning("Failed to parse date string: {$date}", ['exception' => $e]);

            return null;
        }
    }

    /**
     * Converts a date string from one timezone to another, returning the result in DB_FORMAT.
     */
    public static function convert(string $date, string $from_timezone, string $to_timezone): string
    {
        $carbonDate = static::parse($date, $from_timezone);

        return $carbonDate->setTimezone($to_timezone)->format(self::DB_FORMAT);
    }

    /**
     * Converts a date string from a specific timezone to UTC for database storage.
     */
    public static function toUtc(string $date, string $from_timezone): string
    {
        return self::convert($date, $from_timezone, 'UTC');
    }

    /**
     * Returns an array of all PHP-supported timezone identifiers for UI selection.
     */
    public static function getTimezones(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    }

    /**
     * Parses a date string (which may contain a 'to' range) into an array of start and end timestamps (DB_FORMAT).
     */
    public static function prepareDate(string $date): array
    {
        [$from, $to] = array_pad(explode('to', $date), 2, null);
        $to = empty($to) ? $from : $to;

        return [
            'start' => static::parse($from)->startOfDay()->format(self::DB_FORMAT),
            'end' => static::parse($to)->endOfDay()->format(self::DB_FORMAT),
        ];
    }

    /**
     * Gets the start and end datetime for a given single date (DB_FORMAT).
     */
    public static function startAndEndOfDay(string $date): array
    {
        $carbonDate = static::parse($date);

        return [
            'start' => $carbonDate->copy()->startOfDay()->format(self::DB_FORMAT),
            'end' => $carbonDate->copy()->endOfDay()->format(self::DB_FORMAT),
        ];
    }

    /**
     * Gets the start and end datetime for a given date's week (DB_FORMAT).
     */
    public static function startAndEndOfWeek(?string $date = null): array
    {
        $instance = $date ? static::parse($date) : static::now();

        return [
            'start' => $instance->copy()->startOfWeek()->format(self::DB_FORMAT),
            'end' => $instance->copy()->endOfWeek()->format(self::DB_FORMAT),
        ];
    }

    /**
     * Gets the start and end datetime for a given year (DB_FORMAT).
     */
    public static function startAndEndOfYear(int $year): array
    {
        $created_year = static::create($year, 1, 1);

        return [
            'start' => $created_year->copy()->startOfYear()->format(self::DB_FORMAT),
            'end' => $created_year->copy()->endOfYear()->format(self::DB_FORMAT),
        ];
    }

    /**
     * Gets the start and end datetime for a specified quarter and year (DB_FORMAT).
     */
    public static function startAndEndOfQuarter(int $quarter, int $year): array
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException('Quarter must be between 1 and 4.');
        }

        $startMonth = (($quarter - 1) * 3) + 1;

        $carbonDate = static::create($year, $startMonth, 1);

        return [
            'start' => $carbonDate->copy()->startOfQuarter()->format(self::DB_FORMAT),
            'end' => $carbonDate->copy()->endOfQuarter()->format(self::DB_FORMAT),
        ];
    }

    /**
     * Gets the start of the current month and the current datetime.
     */
    public static function startAndNowOfThisMonth(string $format = self::DB_FORMAT): array
    {
        $now = self::now();
        $start = $now->copy()->startOfMonth()->format($format);
        $end = $now->copy()->format($format);

        return compact('start', 'end');
    }

    /**
     * Gets the start and end datetime for the current month.
     */
    public static function startAndEndOfThisMonth(string $format = self::DB_FORMAT): array
    {
        $now = self::now();
        $start = $now->copy()->startOfMonth()->format($format);
        $end = $now->copy()->endOfMonth()->format($format);

        return compact('start', 'end');
    }

    /**
     * Formats a single date string into a user-friendly short date format.
     */
    public static function formatShortDate(string $date): string
    {
        $carbonDate = self::safeParse($date);

        return $carbonDate ? $carbonDate->format(self::SHORT_DATE_FORMAT) : '';
    }

    /**
     * Formats a single date string into a user-friendly datetime format.
     */
    public static function formatFriendlyDatetime(string $date): string
    {
        $carbonDate = self::safeParse($date);

        return $carbonDate ? $carbonDate->format(self::FRIENDLY_DATETIME_FORMAT) : '';
    }

    /**
     * Interprets a date string (single date or range) and returns a formatted string.
     */
    public static function interpreteDate(?string $date, bool $dateonly = true): ?string
    {
        if (empty($date)) {
            return null;
        }

        $dateRange = static::prepareDate($date);

        $startDate = static::parse($dateRange['start']);
        $endDate = static::parse($dateRange['end']);

        if ($dateonly ? $startDate->isSameDay($endDate) : $startDate->eq($endDate)) {
            return self::formatShortDate($dateRange['start']);
        }

        return self::formatShortDate($dateRange['start']) . ' to ' . self::formatShortDate($dateRange['end']);
    }

    /**
     * Calculates the "time ago" string using Carbon's full, localized format,
     * substituting "just now" for differences under one minute.
     */
    public static function timeAgo(string $date, bool $with_tense = true): string
    {
        $carbonDate = self::safeParse($date);
        if (! $carbonDate) {
            return 'No date provided';
        }

        $diffInSeconds = abs($carbonDate->diffInSeconds());

        if ($diffInSeconds < 1) {
            return 'just now';
        }

        if (! $with_tense) {
            return $carbonDate->diffForHumans(null, true);
        }

        return $carbonDate->diffForHumans();
    }

    /**
     * Calculates a compact, short "time ago" string (e.g., '3d', '5h', '1m').
     */
    public static function diffForHumansShort(string $date): string
    {
        $carbonDate = self::safeParse($date);
        if (! $carbonDate) {
            return 'N/A';
        }

        return $carbonDate->diffForHumans([
            'options' => Carbon::JUST_NOW | Carbon::ONE_DAY_WORDS,
            'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
            'short' => true,
            'parts' => 1,
        ]);
    }

    /**
     * Checks if the given date occurs in the future relative to the current time.
     */
    public static function checkIfFuture(string $date): bool
    {
        $carbonDate = self::safeParse($date);

        return $carbonDate ? $carbonDate->isFuture() : false;
    }

    /**
     * Checks if the given date has already occurred relative to the current time.
     */
    public static function checkIfPast(string $date): bool
    {
        $carbonDate = self::safeParse($date);

        return $carbonDate ? $carbonDate->isPast() : false;
    }

    /**
     * Checks if the given date string falls on the current day.
     */
    public static function isDateToday(string $date): bool
    {
        $carbonDate = self::safeParse($date);

        return $carbonDate ? $carbonDate->isToday() : false;
    }

    /**
     * Checks if the given date string falls on yesterday.
     */
    public static function isDateYesterday(string $date): bool
    {
        $carbonDate = self::safeParse($date);

        return $carbonDate ? $carbonDate->isYesterday() : false;
    }

    /**
     * Checks if the given date string falls on tomorrow.
     */
    public static function isDateTomorrow(string $date): bool
    {
        $carbonDate = self::safeParse($date);

        return $carbonDate ? $carbonDate->isTomorrow() : false;
    }

    /**
     * Checks if the given date's month and day match the current month and day.
     */
    public static function isDateBirthday(string $date): bool
    {
        $carbonDate = self::safeParse($date);

        return $carbonDate ? $carbonDate->isBirthday() : false;
    }

    /**
     * Checks if the given date is a Saturday or Sunday.
     */
    public static function isDateWeekend(string $date): bool
    {
        $carbonDate = self::safeParse($date);

        return $carbonDate ? $carbonDate->isWeekend() : false;
    }

    /**
     * Checks if the given date is a weekday (Monday-Friday).
     */
    public static function isDateBusinessDay(string $date): bool
    {
        $carbonDate = self::safeParse($date);

        return $carbonDate ? $carbonDate->isWeekday() : false;
    }

    /**
     * Checks if the given date is a holiday based on a provided array of holiday dates.
     * Compares the Y-m-d format for simple fixed holidays.
     */
    public static function isHoliday(string $date, array $holidayDates): bool
    {
        $carbonDate = self::safeParse($date);
        if (! $carbonDate) {
            return false;
        }

        $checkDate = $carbonDate->format('Y-m-d');

        foreach ($holidayDates as $holiday) {
            $carbonHoliday = self::safeParse($holiday);

            if ($carbonHoliday && $carbonHoliday->format('Y-m-d') === $checkDate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculates the age in years from a birthday string.
     */
    public static function getAge(string $birthday): int
    {
        $carbonBirthday = self::safeParse($birthday);

        return $carbonBirthday ? $carbonBirthday->age : 0;
    }
}
