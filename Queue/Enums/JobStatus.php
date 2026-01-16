<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Enum representing the status of a queued job.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Enums;

enum JobStatus: string
{
    case Pending = 'pending';
    case Failed = 'failed';
    case Success = 'success';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isSuccessful(): bool
    {
        return $this === self::Success;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    public function color(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Failed => 'danger',
            self::Pending => 'info',
        };
    }
}
