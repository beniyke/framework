<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Starts the queue worker daemon.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Commands;

use Core\Services\ConfigServiceInterface;
use Core\Support\Adapters\Interfaces\OSCheckerInterface;
use Exception;
use Helpers\File\Contracts\CacheInterface;
use Queue\Worker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StartWorkerCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('worker:start')
            ->setDescription('Starts the queue worker daemon.')
            ->setHelp('This command starts the worker daemon to begin processing queued jobs.')
            ->addOption('queue', 'w', InputOption::VALUE_OPTIONAL, 'The worker', 'default')
            ->addOption('memory', 'm', InputOption::VALUE_OPTIONAL, 'The worker memory', 128);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = container();
        $config = $container->get(ConfigServiceInterface::class);
        $checkState = (bool) ($config->get('queue.check_state') ?? true);

        $queueName = $input->getOption('queue');
        $memory = $input->getOption('memory');

        $worker = new Worker(
            $container->get(CacheInterface::class),
            $container->get(OSCheckerInterface::class),
            $queueName,
            memoryLimit: $memory,
            checkState: $checkState
        );

        $io = new SymfonyStyle($input, $output);
        $io->title('Queue Worker Manager');
        $io->section('Starting Worker Daemon');

        try {
            $io->text('Initializing worker process...');

            $worker->start();

            $io->success('Worker has been successfully started.');
            $io->note('The worker process is now running in the background and processing jobs.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('A fatal error occurred while attempting to start the worker: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
