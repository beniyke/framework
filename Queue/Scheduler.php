<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Scheduler class is responsible for scheduling jobs by calculating
 * future dates and times based on various time units (days, weeks, months, etc.)
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue;

use Core\Services\ConfigServiceInterface;
use Helpers\DateTimeHelper;
use InvalidArgumentException;

class Scheduler
{
    private DateTimeHelper $date;

    private string $timezone;

    public function __construct(ConfigServiceInterface $config)
    {
        $this->timezone = $config->get('timezone');
        $this->date = DateTimeHelper::now()->setTimezone($this->timezone);
    }

    public function setInitialDate(DateTimeHelper $date): self
    {
        $this->date = $date->setTimezone($this->timezone);

        return $this;
    }

    public function minute(int $minute = 1): self
    {
        $this->date = DateTimeHelper::instance($this->date->addMinutes($minute));

        return $this;
    }

    public function day(int $day = 1): self
    {
        $this->date = DateTimeHelper::instance($this->date->addDays($day));

        return $this;
    }

    public function week(int $week = 1): self
    {
        $this->date = DateTimeHelper::instance($this->date->addWeeks($week));

        return $this;
    }

    public function month(int $month = 1): self
    {
        $this->date = DateTimeHelper::instance($this->date->addMonths($month));

        return $this;
    }

    public function year(int $year = 1): self
    {
        $this->date = DateTimeHelper::instance($this->date->addYears($year));

        return $this;
    }

    public function time(): DateTimeHelper
    {
        return $this->date;
    }

    public function at(string $time): self
    {
        // Parse time in HH:MM or HH:MM:SS format
        $parts = explode(':', $time);

        if (count($parts) < 2 || count($parts) > 3) {
            throw new InvalidArgumentException('Time must be in HH:MM or HH:MM:SS format');
        }

        $hour = (int) $parts[0];
        $minute = (int) $parts[1];
        $second = isset($parts[2]) ? (int) $parts[2] : 0;

        // Validate time components
        if ($hour < 0 || $hour > 23) {
            throw new InvalidArgumentException('Hour must be between 0 and 23');
        }
        if ($minute < 0 || $minute > 59) {
            throw new InvalidArgumentException('Minute must be between 0 and 59');
        }
        if ($second < 0 || $second > 59) {
            throw new InvalidArgumentException('Second must be between 0 and 59');
        }

        $this->date = DateTimeHelper::instance($this->date->setTime($hour, $minute, $second));

        return $this;
    }

    public function period(?DateTimeHelper $initialDate = null): DateTimeHelper
    {
        if ($initialDate) {
            $this->setInitialDate($initialDate);
        }

        return $this->date;
    }
}
