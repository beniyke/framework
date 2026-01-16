<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an existing middleware.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Droppers;

use Cli\Build\Droppers;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteMiddlewareCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('middlewarename', InputArgument::REQUIRED, 'Name Of The Middleware to Delete.')
            ->addOption('api', null, InputOption::VALUE_NONE, 'Delete Middleware from API group.')
            ->addOption('web', null, InputOption::VALUE_NONE, 'Delete Middleware from Web group.')
            ->setName('middleware:delete')
            ->setDescription('Deletes existing Middleware.')
            ->setHelp('This command allows you to delete an existing Middleware...' . PHP_EOL . 'Usage: php dock middleware:delete MyMiddleware --web');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $middlewareName = $input->getArgument('middlewarename');

        $group = 'Web';
        if ($input->getOption('api')) {
            $group = 'Api';
        }

        $io->title('Middleware Dropper');
        $io->note(sprintf('Attempting to delete Middleware "%s" from "%s" group.', $middlewareName, $group));

        try {
            if (! $io->confirm(sprintf('Are you sure you want to delete "%s" middleware from "%s" group?', $middlewareName, $group), false)) {
                $io->text('Deletion cancelled.');

                return self::SUCCESS;
            }
            $dropper = Droppers::getInstance();
            $io->text('Deleting middleware file...');
            $result = $dropper->middleware($middlewareName, $group);

            if ($result['status']) {
                $io->success($result['message']);
            } else {
                $io->error('Deletion Failed: ' . $result['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Middleware Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
