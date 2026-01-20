<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new View Model.
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

class CreateViewModelCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('modelname', InputArgument::REQUIRED, 'Name Of The View Model to Create.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Generate The View Model to.')
            ->setName('view:create-model')
            ->setDescription('Creates new View Model.')
            ->addOption('form', 'f', InputOption::VALUE_NONE, 'Generate a Form ViewModel')
            ->setHelp('This command allows you to create a new View Model...' . PHP_EOL . 'Note: To create a view model for a module, first enter the name of the view model, add a space, then the name of the module e.g. login account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $modelName = $input->getArgument('modelname');
        $moduleName = $input->getArgument('modulename');
        $isForm = (bool) $input->getOption('form');

        $io->title('View Model Generator');
        $io->note(sprintf(
            'Attempting to create View Model Class "%s"%s.',
            $modelName,
            $moduleName ? ' in module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating view model file...');
            $build = $generator->view_model($modelName, $moduleName, $isForm);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $modelName,
                    'Module' => $moduleName ?: '(Global)',
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
            $io->error('Fatal Error during View Model Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
