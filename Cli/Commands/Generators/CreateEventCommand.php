<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new event.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Generators;

use Cli\Build\Generators;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateEventCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('eventname', InputArgument::REQUIRED, 'Name Of The Event to Generate.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Generate The Event to.')
            ->setName('event:create')
            ->setDescription('Creates new Event.')
            ->setHelp('This command allows you to create a new Event...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $eventName = $input->getArgument('eventname');

        if (! str_ends_with($eventName, 'Event')) {
            $eventName .= 'Event';
        }

        $moduleName = $input->getArgument('modulename');

        $io->title('Event Generator');
        $io->note(sprintf(
            'Attempting to create Event "%s" in "%s".',
            $eventName,
            $moduleName ?? 'App/Events'
        ));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating event file...');
            $build = $generator->event($eventName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $eventName,
                    'Location' => $moduleName ?? 'App/Events',
                ];

                if (isset($build['path'])) {
                    $details['File Path'] = $build['path'];
                }

                $io->definitionList($details);
            } else {
                $io->error('Generation Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Event Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
