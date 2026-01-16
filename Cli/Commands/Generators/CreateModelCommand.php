<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new model.
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

class CreateModelCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('modelname', InputArgument::REQUIRED, 'Name Of The Model to Generate.')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Generate The Model to.')
            ->setName('model:create')
            ->setDescription('Creates new model.')
            ->setHelp('This command allows you to create a new model...' . PHP_EOL . 'Note: To create a model for a module, first enter the name of the model, add a space, then the name of the module e.g. login account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $modelName = $input->getArgument('modelname');
        $moduleName = $input->getArgument('modulename');

        $io->title('Model Generator');
        $io->note(sprintf('Attempting to create Model "%s" in module "%s".', $modelName, $moduleName));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating model file...');

            $build = $generator->model($modelName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $modelName,
                    'Module' => $moduleName,
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
            $io->error('Fatal Error during Model Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
