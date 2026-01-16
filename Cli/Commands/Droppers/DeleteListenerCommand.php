<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete a listener.
 */

namespace Cli\Commands\Droppers;

use Cli\Build\Droppers;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteListenerCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('listenername', InputArgument::REQUIRED, 'Name Of The Listener to Delete.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Delete The Listener from.')
            ->setName('listener:delete')
            ->setDescription('Deletes a Listener.')
            ->setHelp('This command allows you to delete a Listener...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $listenerName = $input->getArgument('listenername');

        if (! str_ends_with($listenerName, 'Listener')) {
            $listenerName .= 'Listener';
        }

        $moduleName = $input->getArgument('modulename');

        $io->title('Listener Dropper');
        $io->note(sprintf(
            'Attempting to delete Listener "%s" from "%s".',
            $listenerName,
            $moduleName ?? 'App/Listeners'
        ));

        if (! $io->confirm(sprintf('Are you sure you want to delete listener "%s"?', $listenerName), true)) {
            $io->text('Deletion cancelled.');

            return self::SUCCESS;
        }

        try {
            $dropper = Droppers::getInstance();
            $io->text('Deleting listener file...');
            $build = $dropper->listener($listenerName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);
            } else {
                $io->error('Deletion Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Listener Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
