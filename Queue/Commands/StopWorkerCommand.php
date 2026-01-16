<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Stops the queue worker daemon.
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

class StopWorkerCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('worker:stop')
            ->setDescription('Stops the queue worker daemon.')
            ->setHelp('This command sends a signal to gracefully stop the worker daemon process.')
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
        $io->section('Stopping Worker Daemon');

        try {
            $io->text('Sending stop signal to the worker process...');

            $stop = $worker->stop();

            if ($stop) {
                $io->success('Worker daemon process has been terminated.');
            } else {
                $io->warning('Could not stop the worker.');
                $io->note('This might mean the worker process was not running or is managed externally.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('A fatal error occurred during worker termination: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
