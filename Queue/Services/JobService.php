<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Service for managing queued jobs.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Services;

use Database\Collections\ModelCollection;
use Database\Query\Builder;
use Helpers\DateTimeHelper;
use Queue\Enums\JobStatus;
use Queue\Interfaces\JobServiceInterface;
use Queue\Models\QueuedJob;

class JobService implements JobServiceInterface
{
    public function getAvailableJobsQuery(string $identifier, int $limit): Builder
    {
        return QueuedJob::byIdentifier($identifier)
            ->available()
            ->limit($limit)
            ->oldest('schedule');
    }

    public function getJobById(int $id): Builder
    {
        return QueuedJob::query()
            ->where('id', $id);
    }

    public function getFailedJobs(string $identifier, int $limit, int $maxRetries): ModelCollection
    {
        return QueuedJob::byIdentifier($identifier)
            ->failed()
            ->whereLessThan('failed', $maxRetries)
            ->limit($limit)
            ->oldest('updated_at')
            ->get();
    }

    public function getTotalCount(?JobStatus $status = null, ?string $identifier = null): int
    {
        return QueuedJob::query()
            ->when($identifier, function ($query) use ($identifier) {
                return $query->byIdentifier($identifier);
            })
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status->value);
            })
            ->count();
    }

    public function reserve(QueuedJob $job): bool
    {
        $job->reserved_at = DateTimeHelper::now();
        $job->failed++;

        return $job->save();
    }

    public function release(QueuedJob $job): bool
    {
        $job->reserved_at = null;

        return $job->save();
    }

    public function markFailed(QueuedJob $job, string $response): bool
    {
        $job->status = JobStatus::Failed;
        $job->response = $response;
        $job->reserved_at = null;

        return $job->save();
    }

    public function markSuccess(QueuedJob $job, string $response): bool
    {
        $job->status = JobStatus::Success;
        $job->response = $response;
        $job->reserved_at = null;

        return $job->save();
    }

    public function retry(QueuedJob $job, int $delayMinutes): bool
    {
        $job->status = JobStatus::Pending;
        $job->schedule = DateTimeHelper::now()->addMinutes($delayMinutes);
        $job->reserved_at = null;

        return $job->save();
    }

    public function cleanStuckJobs(int $minutes): int
    {
        $timeoutTime = DateTimeHelper::now()->subMinutes($minutes);

        return QueuedJob::query()
            ->where('status', JobStatus::Pending->value)
            ->whereNotNull('reserved_at')
            ->whereOnOrBefore('reserved_at', $timeoutTime)
            ->update([
                'reserved_at' => null,
                'status' => JobStatus::Pending->value,
            ]);
    }

    public function deleteByStatus(JobStatus $status, ?string $identifier = null): int
    {
        return QueuedJob::query()
            ->when($identifier, function ($query) use ($identifier) {
                return $query->byIdentifier($identifier);
            })
            ->where('status', $status->value)
            ->delete();
    }
}
