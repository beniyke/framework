<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new controller.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Generators;

use Cli\Build\Generators;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateControllerCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('controllername', InputArgument::REQUIRED, 'Name Of The Controller to Generate.')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Generate The Controller to.')
            ->addOption('api', null, InputOption::VALUE_NONE, 'Generate a minimal API-style controller.')
            ->setName('controller:create')
            ->setDescription('Creates new Contoller.')
            ->setHelp('This command allows you to create a new Contoller...' . PHP_EOL . 'Note: To create a controller for a module, first enter the name of the controller, add a space, then the name of the module e.g. login account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $controllerName = $input->getArgument('controllername');
        $moduleName = $input->getArgument('modulename');
        $isApi = $input->getOption('api');

        $io->title('Controller Generator');
        $io->note(sprintf(
            'Attempting to create %s Controller "%s" in module "%s".',
            $isApi ? 'API' : 'standard',
            $controllerName,
            $moduleName
        ));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating controller file...');
            $build = $generator->controller($controllerName, $moduleName, $isApi);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $controllerName,
                    'Module' => $moduleName,
                    'Type' => $isApi ? 'API (Minimal)' : 'Standard',
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
            $io->error('Fatal Error during Controller Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
