<?php

declare(strict_types=1);

namespace Cron;

use Helpers\DateTimeHelper;

class Task
{
    private ?string $signature = null;

    /** @var callable|null */
    private $callback = null;

    private string $expression = '* * * * *';

    private ?string $timezone = null;

    /** @var string[] */
    private array $parsedExpression = ['*', '*', '*', '*', '*'];

    public function __construct(?string $signature = null)
    {
        $this->signature = $signature;
    }

    public function signature(string $signature): self
    {
        $this->signature = $signature;

        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function call(callable $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    public function getCallback(): ?callable
    {
        return $this->callback;
    }

    public function cron(string $expression): self
    {
        $this->expression = $expression;
        $this->parsedExpression = explode(' ', $expression);

        return $this;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): self
    {
        return $this->cron("{$minute} * * * *");
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function at(string $time): self
    {
        $parts = explode(':', $time);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        $currentParts = explode(' ', $this->expression);
        $currentParts[0] = (string) $minute;
        $currentParts[1] = (string) $hour;

        return $this->cron(implode(' ', $currentParts));
    }

    public function dailyAt(string $time): self
    {
        return $this->daily()->at($time);
    }

    public function twiceDaily(int $firstHour = 1, int $secondHour = 13): self
    {
        return $this->cron("0 {$firstHour},{$secondHour} * * *");
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function weekdays(): self
    {
        return $this->cron('0 0 * * 1-5');
    }

    public function weekends(): self
    {
        return $this->cron('0 0 * * 0,6');
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $day, string $time = '00:00'): self
    {
        $this->monthly()->at($time);
        $parts = explode(' ', $this->expression);
        $parts[2] = (string) $day;

        return $this->cron(implode(' ', $parts));
    }

    public function mondays(): self
    {
        return $this->days(1);
    }

    public function tuesdays(): self
    {
        return $this->days(2);
    }

    public function wednesdays(): self
    {
        return $this->days(3);
    }

    public function thursdays(): self
    {
        return $this->days(4);
    }

    public function fridays(): self
    {
        return $this->days(5);
    }

    public function saturdays(): self
    {
        return $this->days(6);
    }

    public function sundays(): self
    {
        return $this->days(0);
    }

    public function days(int|string|array $days): self
    {
        $days = is_array($days) ? implode(',', $days) : (string) $days;
        $parts = explode(' ', $this->expression);
        $parts[4] = $days;

        return $this->cron(implode(' ', $parts));
    }

    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function isDue(DateTimeHelper $now): bool
    {
        if ($this->timezone) {
            $now = $now->setTimezone($this->timezone);
        }

        $currentMinute = (int) $now->format('i');
        $currentHour = (int) $now->format('G');
        $currentDay = (int) $now->format('j');
        $currentMonth = (int) $now->format('n');
        $currentDOW = (int) $now->format('w'); // 0 (Sun) to 6 (Sat)

        if (count($this->parsedExpression) !== 5) {
            return false;
        }

        return $this->checkPart($this->parsedExpression[0], $currentMinute, 0, 59) &&
            $this->checkPart($this->parsedExpression[1], $currentHour, 0, 23) &&
            $this->checkPart($this->parsedExpression[2], $currentDay, 1, 31) &&
            $this->checkPart($this->parsedExpression[3], $currentMonth, 1, 12) &&
            $this->checkPart($this->parsedExpression[4], $currentDOW, 0, 6);
    }

    private function checkPart(string $part, int $current, int $min, int $max): bool
    {
        if ($part === '*') {
            return true;
        }

        // Handle lists (e.g. 1,2,5)
        if (str_contains($part, ',')) {
            foreach (explode(',', $part) as $listPart) {
                if ($this->checkPart($listPart, $current, $min, $max)) {
                    return true;
                }
            }

            return false;
        }

        // Handle steps (e.g. */5 or 1-10/2)
        if (str_contains($part, '/')) {
            [$range, $step] = explode('/', $part);
            $step = (int) $step;

            if ($range === '*') {
                return $current % $step === 0;
            }

            if (str_contains($range, '-')) {
                [$start, $end] = explode('-', $range);
                $start = (int) $start;
                $end = (int) $end;

                if ($current >= $start && $current <= $end) {
                    return ($current - $start) % $step === 0;
                }

                return false;
            }

            // Fallback for weird cases: single value with step
            return (int) $range === $current;
        }

        // Handle ranges (e.g. 1-5)
        if (str_contains($part, '-')) {
            [$start, $end] = explode('-', $part);

            return $current >= (int) $start && $current <= (int) $end;
        }

        // Handle single values
        return (int) $part === $current;
    }
}
