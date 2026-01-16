<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Restarts the queue worker daemon.
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

class RestartWorkerCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('worker:restart')
            ->setDescription('Restarts the queue worker daemon.')
            ->setHelp('This command sends a signal to gracefully restart the worker daemon process.')
            ->addOption('queue', 'w', InputOption::VALUE_OPTIONAL, 'The worker', 'default')
            ->addOption('memory', 'm', InputOption::VALUE_OPTIONAL, 'The worker memory', 128);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = container();
        $queueName = $input->getOption('queue');
        $memory = $input->getOption('memory');

        $worker = new Worker(
            $container->get(CacheInterface::class),
            $container->get(OSCheckerInterface::class),
            $queueName,
            memoryLimit: $memory
        );

        $io = new SymfonyStyle($input, $output);
        $io->title('Queue Worker Manager');
        $io->section('Restarting Worker Daemon');

        try {
            $io->text('Sending restart signal to the worker process...');
            $restart = $worker->restart();

            if ($restart) {
                $io->success('Worker has been successfully restarted.');
                $io->note('The queue worker should now be running with the latest code.');
            } else {
                $io->warning('Couldn\'t restart the worker.');
                $io->note('This usually means no running worker process was found to restart, or the process is managed externally.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('A fatal error occurred during worker restart: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
