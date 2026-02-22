<?php

declare(strict_types=1);

namespace Core\Services;

use Cron\Interfaces\CronInterface;
use Defer\DeferrerInterface;
use Exception;
use Queue\Interfaces\QueueDispatcherInterface;
use Throwable;

class BackgroundDispatcher implements BackgroundDispatcherInterface
{
    private readonly QueueDispatcherInterface $queue;

    private readonly CronInterface $cron;

    private readonly DeferrerInterface $deferrer;

    public function __construct(
        QueueDispatcherInterface $queue,
        CronInterface $cron,
        DeferrerInterface $deferrer
    ) {
        $this->queue = $queue;
        $this->cron = $cron;
        $this->deferrer = $deferrer;
    }

    public function run(): string
    {
        $output = [];

        try {
            $output[] = "--- Queue Processing ---";

            // Re-queue failed jobs
            $output[] = "Failed Jobs: " . $this->queue->failed()->run();

            // Process pending jobs
            $output[] = "Pending Jobs: " . $this->queue->pending()->run();

            $output[] = "\n--- Task Scheduling ---";
            ob_start();
            $this->cron->run();
            $output[] = ob_get_clean();

            $output[] = "\n--- Deferred Tasks ---";
            $output[] = $this->runDeferredTasks();
        } catch (Throwable $e) {
            $output[] = "\n!!! Background Dispatch Error: " . $e->getMessage();
        }

        return implode(PHP_EOL, $output);
    }

    /**
 * Anchor Framework
 *
 * Execute all payloads currently in the deferrer.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */
    private function runDeferredTasks(): string
    {
        if (!$this->deferrer->hasPayload()) {
            return "No deferred tasks to process.";
        }

        $count = 0;
        $failed = 0;
        $payloads = $this->deferrer->getPayloads();
        $this->deferrer->clearPayloads();

        foreach ($payloads as $payload) {
            if (is_callable($payload)) {
                try {
                    $payload();
                    $count++;
                } catch (Exception $e) {
                    $failed++;
                    error_log('Deferred task failed: ' . $e->getMessage());
                }
            }
        }

        return "Successfully executed {$count} deferred tasks." . ($failed > 0 ? " ({$failed} failed)" : "");
    }
}
