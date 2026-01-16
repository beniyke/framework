<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Executes queued jobs based on status.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Commands;

use Exception;
use Queue\Interfaces\QueueDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunQueueCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('queue:run')
            ->addArgument('status', InputArgument::OPTIONAL, 'The job status to run (only "pending" or "failed" is accepted).')
            ->setDescription('Executes queued jobs based on status.')
            ->setHelp('This command executes queued jobs. If no status is specified, it runs pending jobs and attempts to re-run failed jobs.')
            ->addOption('identifier', 'i', InputOption::VALUE_OPTIONAL, 'The identifier', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identifier = $input->getOption('identifier');

        $io = new SymfonyStyle($input, $output);
        $io->title('Queue Job Runner');

        try {
            $queue_status = ['pending', 'failed'];
            $status = $input->getArgument('status');
            $responseMessages = [];

            if (! empty($status) && ! in_array($status, $queue_status)) {
                $io->error(sprintf('Invalid queue status "%s". Status must be one of: %s.', $status, implode(', ', $queue_status)));

                return self::FAILURE;
            }

            $status = empty($status) ? 'all' : $status;

            $io->section(sprintf('Starting job execution for: %s jobs.', $status));
            $io->text('Resolving Queue Dispatcher...');

            $dispatcher = resolve(QueueDispatcherInterface::class);

            if (in_array($status, ['all', 'pending'])) {
                $io->info('Running pending jobs...');
                $responseMessages[] = $dispatcher->pending($identifier)->run();
            }

            if ($status == 'all') {
                $io->info('Scheduling failed job retry run for shutdown...');

                defer(function () use ($dispatcher, $io, $identifier) {
                    $io->info('Attempting to re-run failed jobs...');
                    try {
                        $failedResponse = $dispatcher->failed($identifier)->run();
                        $io->comment('Deferred result: ' . $failedResponse);
                    } catch (Exception $e) {
                        $io->error('Error during deferred failed job run: ' . $e->getMessage());
                    }
                });
            } elseif ($status == 'failed') {
                $io->info('Running failed jobs...');
                $responseMessages[] = $dispatcher->failed($identifier)->run();
            }

            $io->success('Job execution complete. Check deferred logs for failed job retry status.');

            if (! empty($responseMessages)) {
                $io->comment('Primary run results: ' . PHP_EOL . implode(PHP_EOL, $responseMessages));
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('A critical error occurred during queue execution: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
