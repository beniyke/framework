<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete specific view models.
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

class DeleteViewModelCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('modelname', InputArgument::REQUIRED, 'Name Of The View Model(s) to be deleted (comma-separated).')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to delete The View Model from.')
            ->setName('view:delete-model')
            ->setDescription('Deletes specific view model(s).')
            ->setHelp('This command allows you to delete a specified view model...' . PHP_EOL . 'Note: To delete a view model from a module, first enter the name of the view model(s), add a space, then the name of the module. Use commas to delete multiple models. Omitting the module will attempt to delete global view models from "App/Views/Models".');
    }

    protected function deleteViewModelConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('Are you sure you want to delete the specified View Model(s)?', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $viewModelNamesInput = $input->getArgument('modelname');
        $moduleName = $input->getArgument('modulename');

        $io->title('View Model Deletion');
        $io->note(sprintf(
            'Attempting to delete View Model(s) "%s"%s.',
            $viewModelNamesInput,
            $moduleName ? ' from module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $question = $this->deleteViewModelConfirmation();

            if ($io->askQuestion($question)) {

                $dropper = Droppers::getInstance();
                $view_models = explode(',', $viewModelNamesInput);

                $io->section('Deletion Results');

                $rows = [];
                $successCount = 0;
                $failureCount = 0;

                foreach ($view_models as $view_model) {
                    $view_model = trim($view_model);

                    $io->text(sprintf('   Attempting to delete: %s', $view_model));

                    $build = $dropper->view_model(strtolower($view_model), $moduleName);

                    if ($build['status']) {
                        $rows[] = ['✓', $view_model, $build['message']];
                        $successCount++;
                    } else {
                        $rows[] = ['✗', $view_model, $build['message']];
                        $failureCount++;
                    }
                }

                $io->table(['', 'View Model Name', 'Message'], $rows);

                if ($failureCount > 0) {
                    $io->error(sprintf('Finished deletion with %d success(es) and %d failure(s).', $successCount, $failureCount));

                    return Command::FAILURE;
                }

                $io->success(sprintf(
                    'All specified View Models were successfully deleted%s.',
                    $moduleName ? ' from the "' . $moduleName . '" module' : ' (global)'
                ));
            } else {
                $io->comment('Operation cancelled by user. No View Models were deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during View Model Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
