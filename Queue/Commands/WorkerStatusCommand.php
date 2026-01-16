<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Checks the queue worker daemon status.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Commands;

use Core\Support\Adapters\Interfaces\OSCheckerInterface;
use Exception;
use Helpers\File\Contracts\CacheInterface;
use Queue\Worker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WorkerStatusCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('worker:status')
            ->setDescription('Checks the queue worker daemon status.')
            ->setHelp('This command queries the system to check if the worker daemon is currently running or stopped.')
            ->addOption('queue', 'w', InputOption::VALUE_OPTIONAL, 'The worker', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = container();
        $queueName = $input->getOption('queue');

        $worker = new Worker(
            $container->get(CacheInterface::class),
            $container->get(OSCheckerInterface::class),
            $queueName
        );

        $io = new SymfonyStyle($input, $output);

        $io->title('Queue Worker Manager');
        $io->section('Checking Worker Daemon Status');

        try {

            $io->text('Querying system for worker process status...');

            $message = $worker->status();

            $io->success('Status Check Complete');
            $io->writeln("<comment>Worker Status:</comment> <info>{$message}</info>");

            if (str_contains(strtolower($message), 'running')) {
                $io->note('The worker process is currently active and processing jobs.');
            } else {
                $io->note('The worker process is currently inactive or could not be detected.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('A fatal error occurred while checking worker status: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
