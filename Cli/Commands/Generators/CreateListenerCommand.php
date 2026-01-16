<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new listener.
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

class CreateListenerCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('listenername', InputArgument::REQUIRED, 'Name Of The Listener to Generate.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Generate The Listener to.')
            ->setName('listener:create')
            ->setDescription('Creates new Listener.')
            ->setHelp('This command allows you to create a new Listener...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $listenerName = $input->getArgument('listenername');

        if (! str_ends_with($listenerName, 'Listener')) {
            $listenerName .= 'Listener';
        }

        $moduleName = $input->getArgument('modulename');

        $io->title('Listener Generator');
        $io->note(sprintf(
            'Attempting to create Listener "%s" in "%s".',
            $listenerName,
            $moduleName ?? 'App/Listeners'
        ));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating listener file...');
            $build = $generator->listener($listenerName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $listenerName,
                    'Location' => $moduleName ?? 'App/Listeners',
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
            $io->error('Fatal Error during Listener Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
