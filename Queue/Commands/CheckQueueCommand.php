<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Checks and counts queued jobs by status.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Commands;

use Exception;
use Queue\Enums\JobStatus;
use Queue\Interfaces\JobServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckQueueCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('queue:check')
            ->addArgument('status', InputArgument::REQUIRED, 'The job status (e.g., pending, running, failed, success)')
            ->setDescription('Checks and counts queued jobs by status.')
            ->setHelp('This command gets the total number of jobs for a given status.')
            ->addOption('identifier', 'i', InputOption::VALUE_OPTIONAL, 'The identifier', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identifier = $input->getOption('identifier');

        $io = new SymfonyStyle($input, $output);
        $statusString = $input->getArgument('status');

        $io->title('Queue Job Status Checker');

        try {
            $jobService = resolve(JobServiceInterface::class);
            $statusEnum = null;
            $displayStatus = 'all';

            if ($statusString !== 'all') {
                $validStatuses = array_map(fn ($enum) => $enum->value, JobStatus::cases());

                if (! in_array($statusString, $validStatuses)) {
                    $io->error(sprintf('Invalid status "%s". Must be "all" or one of: %s.', $statusString, implode(', ', $validStatuses)));

                    return self::FAILURE;
                }

                $statusEnum = JobStatus::from($statusString);
                $displayStatus = $statusString == 'success' ? 'successful' : $statusString;
            }

            $io->section(sprintf('Querying for jobs with status: "%s"', $displayStatus));
            $io->text('Connecting to job repository...');

            $total = $jobService->getTotalCount($statusEnum, $identifier);

            $message = sprintf('%d %s %s found.', $total, $displayStatus, inflect('job', $total));

            $io->newLine();
            $io->success($message);
            $io->note('Total jobs matching the criteria have been counted.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('An error occurred while checking the queue: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
