<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Pauses queue processing without stopping the worker daemon.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Commands;

use Core\Support\Adapters\Interfaces\OSCheckerInterface;
use Exception;
use Helpers\File\Contracts\CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PauseQueueCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('queue:pause')
            ->setDescription('Pauses queue processing without stopping the worker daemon.')
            ->setHelp('This command pauses job processing for a specific queue or all queues.' . PHP_EOL . 'The worker daemon remains running and can be resumed at any time.')
            ->addOption('identifier', 'i', InputOption::VALUE_OPTIONAL, 'The queue identifier to pause (if not specified, pauses all queues)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = container();
        $queueIdentifier = $input->getOption('identifier');
        $cache = $container->get(CacheInterface::class);
        $osChecker = $container->get(OSCheckerInterface::class);

        $io = new SymfonyStyle($input, $output);
        $io->title('Queue Pause Manager');

        try {
            $cacheWithPath = $cache->withPath('worker');

            if ($queueIdentifier) {
                $io->section("Pausing queue: {$queueIdentifier}");

                $cacheKey = "worker_status_{$queueIdentifier}";

                if (! $cacheWithPath->has($cacheKey)) {
                    $io->warning("Queue '{$queueIdentifier}' is not running.");

                    return self::FAILURE;
                }

                $content = $cacheWithPath->read($cacheKey);
                if ($content === 'pause') {
                    $io->warning("Queue '{$queueIdentifier}' is already paused.");

                    return self::SUCCESS;
                }

                if ($this->isWorkerStale($content)) {
                    $io->warning("Worker for queue '{$queueIdentifier}' appears to be unresponsive (last active: {$content}).");
                    if (! $io->confirm('Do you want to force pause?', false)) {
                        return self::SUCCESS;
                    }
                }

                $cacheWithPath->write($cacheKey, 'pause');

                if ($cacheWithPath->read($cacheKey) !== 'pause') {
                    $io->error("Failed to pause queue '{$queueIdentifier}'. Verification failed.");

                    return self::FAILURE;
                }

                $io->success("Queue '{$queueIdentifier}' has been paused successfully.");
                $io->note('The worker daemon is still running but will not process jobs until resumed.');

                return self::SUCCESS;
            } else {
                $io->section('Pausing all queues');

                $runningQueues = $this->getAllRunningQueues($cache);

                if (empty($runningQueues)) {
                    $io->warning('No running queues found.');

                    return self::SUCCESS;
                }

                $pausedCount = 0;
                $rows = [];

                foreach ($runningQueues as $queueName) {
                    $cacheKey = "worker_status_{$queueName}";
                    $content = $cacheWithPath->read($cacheKey);

                    if ($content === 'pause') {
                        $rows[] = ['⊘', $queueName, 'Already paused'];

                        continue;
                    }

                    if ($this->isWorkerStale($content)) {
                        $io->warning("Worker for queue '{$queueName}' appears to be unresponsive.");
                        if (! $io->confirm("Do you want to force pause queue '{$queueName}'?", false)) {
                            $rows[] = ['-', $queueName, 'Skipped (Stale)'];

                            continue;
                        }
                    }

                    $cacheWithPath->write($cacheKey, 'pause');

                    if ($cacheWithPath->read($cacheKey) === 'pause') {
                        $rows[] = ['✓', $queueName, 'Paused'];
                        $pausedCount++;
                    } else {
                        $rows[] = ['✗', $queueName, 'Failed to pause'];
                    }
                }

                $io->table(['', 'Queue', 'Status'], $rows);
                $io->success("Paused {$pausedCount} of " . count($runningQueues) . ' queue(s).');
                $io->note('Worker daemons are still running but will not process jobs until resumed.');

                return self::SUCCESS;
            }
        } catch (Exception $e) {
            $io->error('A fatal error occurred while attempting to pause queue(s): ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function getAllRunningQueues(CacheInterface $cache): array
    {
        $cacheWithPath = $cache->withPath('worker');
        $allKeys = $cacheWithPath->keys();
        $runningQueues = [];

        foreach ($allKeys as $key) {
            if (strpos($key, 'worker_status_') === 0) {
                $queueName = str_replace('worker_status_', '', $key);
                $runningQueues[] = $queueName;
            }
        }

        return $runningQueues;
    }

    private function isWorkerStale(string $lastActive): bool
    {
        if (strtotime($lastActive) === false) {
            return false;
        }

        return (time() - strtotime($lastActive)) > 60;
    }
}
