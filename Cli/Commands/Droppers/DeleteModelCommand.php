<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an existing model.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Droppers;

use Cli\Build\Droppers;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteModelCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('modelname', InputArgument::REQUIRED, 'Name Of The Model to be Deleted.')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Delete Model From.')
            ->setName('model:delete')
            ->setDescription('Deletes existing model.')
            ->setHelp('This command allows you to delete an existing model...' . PHP_EOL . 'Note: To delete a model from a module, first enter the name of the model, add a space, then the name of the module e.g login account');
    }

    protected function deleteConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=yellow>Are you sure you want to delete this model file? [y]/n </>', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $modelToDelete = $input->getArgument('modelname');
        $moduleName = $input->getArgument('modulename');

        $io->title('Model Deletion');
        $io->note(sprintf('Attempting to delete model "%s" from module "%s".', $modelToDelete, $moduleName));

        try {
            $question = $this->deleteConfirmation();

            if ($io->askQuestion($question)) {
                $io->text('Deletion confirmed. Executing...');

                $dropper = Droppers::getInstance();
                $build = $dropper->model($modelToDelete, $moduleName);

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->warning('Deletion Failed: ' . $build['message']);
                }
            } else {
                $io->comment('Operation cancelled by user. Model was not deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Model Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
