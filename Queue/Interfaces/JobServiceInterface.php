<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for the Job Service.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Interfaces;

use Database\Collections\ModelCollection;
use Database\Query\Builder;
use Queue\Enums\JobStatus;
use Queue\Models\QueuedJob;

interface JobServiceInterface
{
    public function getAvailableJobsQuery(string $identifier, int $limit): Builder;

    public function getJobById(int $id): Builder;

    public function getTotalCount(?JobStatus $status = null, ?string $identifier = null): int;

    public function reserve(QueuedJob $job): bool;

    public function release(QueuedJob $job): bool;

    public function markFailed(QueuedJob $job, string $response): bool;

    public function markSuccess(QueuedJob $job, string $response): bool;

    public function retry(QueuedJob $job, int $delayMinutes): bool;

    public function cleanStuckJobs(int $minutes): int;

    public function deleteByStatus(JobStatus $status, ?string $identifier = null): int;

    public function getFailedJobs(string $identifier, int $limit, int $maxRetries): ModelCollection;
}
