<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Executes queued jobs, using the DB facade for safe, transactional processing.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue;

use Core\Services\ConfigServiceInterface;
use Database\DB;
use Exception;
use Helpers\Data;
use Helpers\File\Contracts\CacheInterface;
use Queue\Enums\JobStatus;
use Queue\Interfaces\JobServiceInterface;
use Queue\Interfaces\QueueDispatcherInterface;
use Queue\Models\QueuedJob;
use RuntimeException;
use Throwable;

class QueueDispatcher implements QueueDispatcherInterface
{
    private ?string $type = null;

    private int $batchSize;

    private string $identifier = 'default';

    private JobServiceInterface $jobService;

    private QueueManager $manager;

    private CacheInterface $cache;

    private int $maxRetries;

    private int $stuckTimeoutMinutes;

    private int $delayMinutes;

    private bool $checkState;

    public function __construct(ConfigServiceInterface $config, JobServiceInterface $jobService, QueueManager $manager, CacheInterface $cache)
    {
        $this->jobService = $jobService;
        $this->manager = $manager;
        $this->cache = $cache;
        $this->batchSize = (int) ($config->get('queue.batch_size') ?? 10);
        $this->maxRetries = (int) ($config->get('queue.max_retry') ?? 3);
        $this->stuckTimeoutMinutes = (int) ($config->get('queue.timeout_minutes') ?? 5);
        $this->delayMinutes = (int) ($config->get('queue.delay_minutes') ?? 5);
        $this->checkState = (bool) ($config->get('queue.check_state') ?? true);
    }

    public function pending(?string $identifier = null): self
    {
        $this->type = JobStatus::Pending->value;
        $this->identifier = $identifier ?? 'default';

        return $this;
    }

    public function failed(?string $identifier = null): self
    {
        $this->type = JobStatus::Failed->value;
        $this->identifier = $identifier ?? 'default';

        return $this;
    }

    public function run(): string
    {
        if ($this->type === null) {
            return 'Error: Job type (pending/failed) not set.';
        }

        if ($this->checkState) {
            $cacheWithPath = $this->cache->withPath('worker');
            $cacheKey = "worker_status_{$this->identifier}";

            if ($cacheWithPath->has($cacheKey) && $cacheWithPath->read($cacheKey) === 'pause') {
                return "Queue '{$this->identifier}' is currently PAUSED. Skipping execution.";
            }
        }

        $jobsProcessed = 0;
        $cleanupCount = $this->jobService->cleanStuckJobs($this->stuckTimeoutMinutes);

        if ($this->type === JobStatus::Failed->value) {
            $jobsProcessed = $this->processFailedJobs();
        } elseif ($this->type === JobStatus::Pending->value) {
            $jobsProcessed = $this->processPendingJobs();
        }

        $type = ucfirst($this->type);
        $statusMessage = ($this->type === JobStatus::Pending->value) ? 'executed!' : 'requeued for another retry.';

        return "Stuck job cleanup completed. {$cleanupCount} jobs released." . PHP_EOL . "{$type} jobs processed: {$jobsProcessed}. Status: {$statusMessage}";
    }

    private function processPendingJobs(): int
    {
        $count = 0;

        $query = $this->jobService->getAvailableJobsQuery($this->identifier, $this->batchSize);
        $jobs = $query->get();

        if (empty($jobs)) {
            return 0;
        }

        foreach ($jobs as $job) {
            $jobId = $job->id;
            try {
                DB::transaction(function () use ($jobId, &$count) {
                    $lockedJob = $this->jobService->getJobById($jobId)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedJob === null || $lockedJob->reserved_at !== null) {
                        return;
                    }

                    if ($lockedJob->status->value !== JobStatus::Pending->value) {
                        return;
                    }

                    if (! $this->jobService->reserve($lockedJob)) {
                        throw new RuntimeException("Failed to reserve locked job ID: {$lockedJob->id}");
                    }

                    $this->executeJobTask($lockedJob);
                    $this->jobService->markSuccess($lockedJob, 'Job completed successfully.');
                    $count++;
                });
            } catch (Throwable $e) {
                $jobForFailure = $this->jobService->getJobById($jobId)->first();

                if ($jobForFailure !== null) {
                    $this->handleFailure($jobForFailure, $e);
                } else {
                    error_log("Job ID {$jobId} failed but was not found for failure handling (likely deleted mid-transaction).");
                }
            }
        }

        return $count;
    }

    private function executeJobTask(QueuedJob $job): void
    {
        $payload = json_decode($job->payload);

        if (json_last_error() !== JSON_ERROR_NONE || ! isset($payload->namespace, $payload->data)) {
            throw new RuntimeException('Invalid job payload format or JSON decode error.');
        }

        $taskClass = $payload->namespace;
        if (! class_exists($taskClass)) {
            throw new RuntimeException('Task class does not exist: ' . $taskClass);
        }

        // Unserialize with allowed classes for security
        $allowedClasses = [$taskClass, 'stdClass'];

        $taskData = @unserialize($payload->data, ['allowed_classes' => $allowedClasses]);

        if ($taskData === false && $payload->data !== 'b:0;') {
            throw new RuntimeException('Failed to unserialize task data.');
        }

        $payload = Data::make($taskData);

        $taskInstance = new $taskClass($payload);
        $response = $taskInstance->run();

        if (($response->status ?? JobStatus::Success->value) === JobStatus::Failed->value) {
            throw new Exception($response->message ?? 'Task reported internal failure.');
        }

        if (method_exists($taskInstance, 'occurrence') && $taskInstance->occurrence() === 'always') {
            $this->deferTask($payload);
        }
    }

    private function processFailedJobs(): int
    {
        $jobs = $this->jobService->getFailedJobs($this->identifier, $this->batchSize, $this->maxRetries);
        $count = 0;

        foreach ($jobs as $job) {
            $this->jobService->retry($job, $this->delayMinutes);
            $count++;
        }

        return $count;
    }

    private function handleFailure(QueuedJob $job, Throwable $e): void
    {
        $response = 'Execution failed. Error: ' . $e->getMessage();

        if ($job->failed < $this->maxRetries) {
            $this->jobService->retry($job, $this->delayMinutes);
            error_log("Job ID {$job->id} failed (attempt {$job->failed}), scheduled for retry.");
        } else {
            $this->jobService->markFailed($job, $response);
            error_log("Job ID {$job->id} permanently failed: {$response}");
        }
    }

    private function deferTask(object $payload): void
    {
        DB::afterCommit(function () use ($payload) {
            // Unserialize with allowed classes
            $allowedClasses = [$payload->namespace, 'stdClass'];
            $originalData = @unserialize($payload->data, ['allowed_classes' => $allowedClasses]);

            if ($originalData === false && $payload->data !== 'b:0;') {
                throw new RuntimeException('Failed to unserialize deferred task data.');
            }

            $this->manager
                ->identifier($this->identifier)
                ->job($payload->namespace, $originalData)
                ->queue();
        });
    }
}
