<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an event.
 */

namespace Cli\Commands\Droppers;

use Cli\Build\Droppers;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteEventCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('eventname', InputArgument::REQUIRED, 'Name Of The Event to Delete.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Delete The Event from.')
            ->setName('event:delete')
            ->setDescription('Deletes an Event.')
            ->setHelp('This command allows you to delete an Event...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $eventName = $input->getArgument('eventname');

        if (! str_ends_with($eventName, 'Event')) {
            $eventName .= 'Event';
        }

        $moduleName = $input->getArgument('modulename');

        $io->title('Event Dropper');
        $io->note(sprintf(
            'Attempting to delete Event "%s" from "%s".',
            $eventName,
            $moduleName ?? 'App/Events'
        ));

        if (! $io->confirm(sprintf('Are you sure you want to delete event "%s"?', $eventName), true)) {
            $io->text('Deletion cancelled.');

            return self::SUCCESS;
        }

        try {
            $dropper = Droppers::getInstance();
            $io->text('Deleting event file...');
            $build = $dropper->event($eventName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);
            } else {
                $io->error('Deletion Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Event Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
