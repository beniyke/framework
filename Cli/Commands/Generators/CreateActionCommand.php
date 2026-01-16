<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new action.
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

class CreateActionCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('actionname', InputArgument::REQUIRED, 'Name Of The Action to Generate.')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Generate The Action to.')
            ->setName('action:create')
            ->setDescription('Creates new Action.')
            ->setHelp('This command allows you to create a new Action...' . PHP_EOL . 'Note: To create a Action for a module, first enter the name of the Action, add a space, then the name of the module e.g login account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $actionName = $input->getArgument('actionname');
        $moduleName = $input->getArgument('modulename');

        $io->title('Action Generator');
        $io->note(sprintf('Attempting to create Action "%s" in module "%s".', $actionName, $moduleName));

        try {
            $generator = Generators::getInstance();

            $io->text('Generating file...');

            $build = $generator->action($actionName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);

                if (isset($build['path'])) {
                    $io->definitionList([
                        'Path' => $build['path'],
                        'Module' => $moduleName,
                    ]);
                }
            } else {
                $io->error('Generation Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Action Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
