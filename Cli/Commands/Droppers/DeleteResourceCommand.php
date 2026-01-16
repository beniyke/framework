<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an existing resource.
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

class DeleteResourceCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('resource_name', InputArgument::REQUIRED, 'Name Of the Resource(s) to be Deleted (comma-separated).')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Delete the Resource from.')
            ->setName('resource:delete')
            ->setDescription('Deletes Existing Resource(s).')
            ->setHelp('This command allows you to delete an existing Resource...' . PHP_EOL . 'Note: To delete a Resource from a module, first enter the name of the Resource(s), add a space, then the name of the module e.g. login account. Use commas to delete multiple resources.');
    }

    protected function deleteConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=yellow>Are you sure you want to delete the specified Resource(s)? [y]/n </>', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $resourceNamesInput = $input->getArgument('resource_name');
        $moduleName = $input->getArgument('modulename');

        $io->title('Resource Deletion');
        $io->note(sprintf('Attempting to delete resource(s) "%s" from module "%s".', $resourceNamesInput, $moduleName));

        try {
            $question = $this->deleteConfirmation();

            if ($io->askQuestion($question)) {

                $dropper = Droppers::getInstance();
                $resources_name = explode(',', $resourceNamesInput);

                $io->section('Deletion Results');

                $rows = [];
                $successCount = 0;
                $failureCount = 0;

                foreach ($resources_name as $resource_name) {
                    $resource_name = trim($resource_name);

                    $io->text(sprintf('   Attempting to delete: <info>%s</info>', $resource_name));

                    $build = $dropper->resource(strtolower($resource_name), $moduleName);

                    if ($build['status']) {
                        $rows[] = ['<info>✓</info>', $resource_name, $build['message']];
                        $successCount++;
                    } else {
                        $rows[] = ['<error>✗</error>', $resource_name, $build['message']];
                        $failureCount++;
                    }
                }

                $io->table(['', 'Resource Name', 'Message'], $rows);

                if ($failureCount > 0) {
                    $io->error(sprintf('Finished deletion with %d success(es) and %d failure(s).', $successCount, $failureCount));

                    return Command::FAILURE;
                }

                $io->success(sprintf('All specified resources were successfully deleted from the "%s" module.', $moduleName));
            } else {
                $io->comment('Operation cancelled by user. No resources were deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Resource Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
