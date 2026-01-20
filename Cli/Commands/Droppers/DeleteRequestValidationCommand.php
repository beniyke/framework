<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete existing Request validations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Droppers;

use Cli\Build\Droppers;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteRequestValidationCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('validation_name', InputArgument::REQUIRED, 'Name Of the Request validation(s) to be Deleted (comma-separated).')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Delete the Request Validation from.')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'The type of validation (form or api)')
            ->setName('validation:delete')
            ->setDescription('Deletes Existing Request validation(s).')
            ->setHelp('This command allows you to delete an existing Request Validation...' . PHP_EOL . 'Note: To delete a request validation from a module, first enter the name of the request validation(s), add a space, then the name of the module, and specify the type using --type=form or --type=api' . PHP_EOL . 'Example: php dock validation:delete Login Auth --type=form' . PHP_EOL . 'Use commas to delete multiple request validations. Omitting the module will attempt to delete global validations from "App/Validations".');
    }

    protected function deleteConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('Are you sure you want to delete the specified Request Validation(s)?', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $validationNamesInput = $input->getArgument('validation_name');
        $moduleName = $input->getArgument('modulename');
        $validationType = $input->getOption('type');

        // Validate that --type option is provided
        if (! $validationType) {
            $io->error('The --type option is required. Please specify either --type=form or --type=api');

            return self::FAILURE;
        }

        // Validate that type is either 'form' or 'api'
        $allowedTypes = ['form', 'api'];
        if (! in_array(strtolower($validationType), $allowedTypes)) {
            $io->error(sprintf('Invalid type "%s". Allowed types are: %s', $validationType, implode(', ', $allowedTypes)));

            return self::FAILURE;
        }

        $io->title('Request Validation Deletion');
        $io->note(sprintf(
            'Attempting to delete %s validation(s) "%s"%s.',
            ucfirst(strtolower($validationType)),
            $validationNamesInput,
            $moduleName ? ' from module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $question = $this->deleteConfirmation();

            if ($io->askQuestion($question)) {
                $dropper = Droppers::getInstance();
                $request_validations_name = explode(',', $validationNamesInput);

                $io->section('Deletion Results');

                $rows = [];
                $successCount = 0;
                $failureCount = 0;

                foreach ($request_validations_name as $request_validation_name) {
                    $request_validation_name = trim($request_validation_name);

                    $io->text(sprintf('   Attempting to delete: %s', $request_validation_name));

                    $build = $dropper->requestValidation(strtolower($request_validation_name), strtolower($validationType), $moduleName);

                    if ($build['status']) {
                        $rows[] = ['✓', $request_validation_name, $build['message']];
                        $successCount++;
                    } else {
                        $rows[] = ['✗', $request_validation_name, $build['message']];
                        $failureCount++;
                    }
                }

                $io->table(['', 'Validation Name', 'Message'], $rows);

                if ($failureCount > 0) {
                    $io->error(sprintf('Finished deletion with %d success(es) and %d failure(s).', $successCount, $failureCount));

                    return Command::FAILURE;
                }

                $io->success(sprintf(
                    'All specified %s validations were successfully deleted%s.',
                    strtolower($validationType),
                    $moduleName ? ' from the "' . $moduleName . '" module' : ' (global)'
                ));
            } else {
                $io->comment('Operation cancelled by user. No form validations were deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Form Validation Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
