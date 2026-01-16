<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to flush queued jobs based on status.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Runners;

use Exception;
use Queue\Enums\JobStatus;
use Queue\Interfaces\JobServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FlushQueueCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('queue:flush')
            ->addArgument('status', InputArgument::OPTIONAL, 'The job status to clear (e.g., pending, failed, success). Defaults to clearing all jobs.')
            ->setDescription('Clears queued jobs matching a specific status.')
            ->setHelp('This command clears queued jobs based on their status or clears all jobs if no status is specified.')
            ->addOption('identifier', 'i', InputOption::VALUE_OPTIONAL, 'The identifier', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identifier = $input->getOption('identifier');

        $io = new SymfonyStyle($input, $output);
        $statusString = $input->getArgument('status');

        $io->title('Queue Job Flusher');

        try {
            $jobService = resolve(JobServiceInterface::class);
            $validStatuses = array_map(fn ($enum) => $enum->value, JobStatus::cases());

            if (empty($statusString)) {
                $io->error('The status argument is required for safe deletion (e.g., pending, failed, success).');

                return self::FAILURE;
            }

            if (! in_array($statusString, $validStatuses)) {
                $io->error(sprintf('Invalid status "%s". Must be one of: %s.', $statusString, implode(', ', $validStatuses)));

                return self::FAILURE;
            }

            $statusEnum = JobStatus::from($statusString);
            $displayStatus = $statusString == 'success' ? 'successful' : $statusString;

            $io->section(sprintf('Attempting to flush "%s" jobs.', $displayStatus));
            $io->text('Initiating deletion via job service...');

            $deletedCount = $jobService->deleteByStatus($statusEnum, $identifier);

            if ($deletedCount > 0) {
                $io->success(sprintf('Successfully cleared %d "%s" jobs from the queue.', $deletedCount, $displayStatus));
            } else {
                $io->note(sprintf('No "%s" jobs were found to clear.', $displayStatus));
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('A fatal error occurred during queue flushing: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
