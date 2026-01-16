<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Model representing a queued job in the database.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Models;

use Database\BaseModel;
use Database\Query\Builder;
use Helpers\DateTimeHelper;
use Queue\Enums\JobStatus;

class QueuedJob extends BaseModel
{
    protected string $table = 'queued_job';

    protected array $fillable = ['identifier', 'payload', 'status', 'response', 'failed', 'schedule', 'reserved_at'];

    protected array $casts = [
        'payload' => 'string',
        'response' => 'string',
        'status' => JobStatus::class,
        'failed' => 'integer',
        'schedule' => 'datetime',
        'reserved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', JobStatus::Pending->value)
            ->whereOnOrBefore('schedule', DateTimeHelper::now())
            ->whereNull('reserved_at');
    }

    public function scopeStuck(Builder $query, int $minutes = 5): Builder
    {
        $timeoutTime = DateTimeHelper::now()->subMinutes($minutes);

        return $query->where('status', JobStatus::Pending->value)
            ->whereNotNull('reserved_at')
            ->whereOnOrBefore('reserved_at', $timeoutTime);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', JobStatus::Failed->value);
    }

    public function scopeByIdentifier(Builder $query, string $identifier): Builder
    {
        return $query->where('identifier', $identifier);
    }

    public static function queue(string $identifier, string $payload, ?DateTimeHelper $schedule = null): self
    {
        return self::create([
            'identifier' => $identifier,
            'payload' => $payload,
            'schedule' => $schedule ?? DateTimeHelper::now(),
            'status' => JobStatus::Pending,
            'reserved_at' => null,
        ]);
    }
}
