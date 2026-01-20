<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete a Request DTO.
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

class DeleteRequestCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('request_name', InputArgument::REQUIRED, 'Name Of the Request DTO(s) to be Deleted (comma-separated).')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Delete the Request DTO from.')
            ->setName('request:delete')
            ->setDescription('Deletes Existing Request DTO(s).')
            ->setHelp('This command allows you to delete an existing Request DTO...' . PHP_EOL . 'Note: To delete a request dto from a module, first enter the name of the request dto(s), add a space, then the name of the module e.g. login account. Use commas to delete multiple DTOs. Omitting the module will attempt to delete global Request DTOs from "App/Requests".');
    }

    protected function deleteConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('Are you sure you want to delete the specified Request DTO(s)?', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $requestNamesInput = $input->getArgument('request_name');
        $moduleName = $input->getArgument('modulename');

        $io->title('Request DTO Deletion');
        $io->note(sprintf(
            'Attempting to delete Request DTO(s) "%s"%s.',
            $requestNamesInput,
            $moduleName ? ' from module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $question = $this->deleteConfirmation();

            if ($io->askQuestion($question)) {

                $dropper = Droppers::getInstance();
                $requests_name = explode(',', $requestNamesInput);

                $io->section('Deletion Results');

                $rows = [];
                $successCount = 0;
                $failureCount = 0;

                foreach ($requests_name as $request_name) {
                    $request_name = trim($request_name);

                    $io->text(sprintf('   Attempting to delete: %s', $request_name));

                    $build = $dropper->request(strtolower($request_name), $moduleName);

                    if ($build['status']) {
                        $rows[] = ['✓', $request_name, $build['message']];
                        $successCount++;
                    } else {
                        $rows[] = ['✗', $request_name, $build['message']];
                        $failureCount++;
                    }
                }

                $io->table(['', 'Request DTO Name', 'Message'], $rows);

                if ($failureCount > 0) {
                    $io->error(sprintf('Finished deletion with %d success(es) and %d failure(s).', $successCount, $failureCount));

                    return Command::FAILURE;
                }

                $io->success(sprintf(
                    'All specified Request DTOs were successfully deleted%s.',
                    $moduleName ? ' from the "' . $moduleName . '" module' : ' (global)'
                ));
            } else {
                $io->comment('Operation cancelled by user. No Request DTOs were deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Request DTO Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
