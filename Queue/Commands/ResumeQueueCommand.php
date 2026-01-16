<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Resumes paused queue processing.
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

class ResumeQueueCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('queue:resume')
            ->setDescription('Resumes paused queue processing.')
            ->setHelp('This command resumes job processing for a paused queue or all paused queues.')
            ->addOption('identifier', 'i', InputOption::VALUE_OPTIONAL, 'The queue identifier to resume (if not specified, resumes all paused queues)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = container();
        $queueIdentifier = $input->getOption('identifier');
        $cache = $container->get(CacheInterface::class);
        $osChecker = $container->get(OSCheckerInterface::class);

        $io = new SymfonyStyle($input, $output);
        $io->title('Queue Resume Manager');

        try {
            $cacheWithPath = $cache->withPath('worker');

            if ($queueIdentifier) {
                $io->section("Resuming queue: {$queueIdentifier}");
                $cacheKey = "worker_status_{$queueIdentifier}";

                if (! $cacheWithPath->has($cacheKey)) {
                    $io->warning("Queue '{$queueIdentifier}' is not running.");

                    return self::FAILURE;
                }

                $content = $cacheWithPath->read($cacheKey);
                if ($content !== 'pause') {
                    $io->warning("Queue '{$queueIdentifier}' is not paused.");

                    return self::SUCCESS;
                }

                $cacheWithPath->write($cacheKey, date('Y-m-d H:i:s'));

                if ($cacheWithPath->read($cacheKey) === 'pause') {
                    $io->error("Failed to resume queue '{$queueIdentifier}'. Verification failed.");

                    return self::FAILURE;
                }

                $io->success("Queue '{$queueIdentifier}' has been resumed successfully.");
                $io->note('The worker will now continue processing jobs.');

                return self::SUCCESS;
            } else {
                $io->section('Resuming all paused queues');

                $pausedQueues = $this->getAllPausedQueues($cache, $osChecker);

                if (empty($pausedQueues)) {
                    $io->warning('No paused queues found.');

                    return self::SUCCESS;
                }

                $resumedCount = 0;
                $rows = [];

                foreach ($pausedQueues as $queueName) {
                    $cacheKey = "worker_status_{$queueName}";
                    $cacheWithPath->write($cacheKey, date('Y-m-d H:i:s'));

                    if ($cacheWithPath->read($cacheKey) !== 'pause') {
                        $rows[] = ['✓', $queueName, 'Resumed'];
                        $resumedCount++;
                    } else {
                        $rows[] = ['✗', $queueName, 'Failed to resume'];
                    }
                }

                $io->table(['', 'Queue', 'Status'], $rows);
                $io->success("Resumed {$resumedCount} of " . count($pausedQueues) . ' queue(s).');
                $io->note('Workers will now continue processing jobs.');

                return self::SUCCESS;
            }
        } catch (Exception $e) {
            $io->error('A fatal error occurred while attempting to resume queue(s): ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function getAllPausedQueues(CacheInterface $cache, OSCheckerInterface $osChecker): array
    {
        $cacheWithPath = $cache->withPath('worker');
        $allKeys = $cacheWithPath->keys();
        $pausedQueues = [];

        foreach ($allKeys as $key) {
            if (strpos($key, 'worker_status_') === 0) {
                $content = $cacheWithPath->read($key);
                if ($content === 'pause') {
                    $queueName = str_replace('worker_status_', '', $key);
                    $pausedQueues[] = $queueName;
                }
            }
        }

        return $pausedQueues;
    }
}
