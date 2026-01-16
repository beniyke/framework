<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an existing action.
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

class DeleteActionCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('action_name', InputArgument::REQUIRED, 'Name Of the Action(s) to be Deleted (comma-separated).')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Delete the Action from.')
            ->setName('action:delete')
            ->setDescription('Deletes Existing Action(s).')
            ->setHelp('This command allows you to delete an existing Action...' . PHP_EOL . 'Note: To delete a Action from a module, first enter the name of the action, add a space, then the name of the module e.g. login account. You can delete multiple actions by comma-separating their names.');
    }

    protected function deleteConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=yellow>Are you sure you want to delete the specified Action(s)? [y]/n </>', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $actionNamesInput = $input->getArgument('action_name');
        $moduleName = $input->getArgument('modulename');

        $io->title('Action Deletion');
        $io->note(sprintf('Attempting to delete action(s) "%s" from module "%s".', $actionNamesInput, $moduleName));

        try {
            $question = $this->deleteConfirmation();

            if ($io->askQuestion($question)) {
                $dropper = Droppers::getInstance();
                $actions_name = explode(',', $actionNamesInput);

                $io->section('Deletion Results');

                $rows = [];
                $successCount = 0;
                $failureCount = 0;

                foreach ($actions_name as $action_name) {
                    $action_name = trim($action_name);

                    $io->text(sprintf('   Attempting to delete: <info>%s</info>', $action_name));

                    $build = $dropper->action(strtolower($action_name), $moduleName);

                    if ($build['status']) {
                        $rows[] = ['<info>✓</info>', $action_name, $build['message']];
                        $successCount++;
                    } else {
                        $rows[] = ['<error>✗</error>', $action_name, $build['message']];
                        $failureCount++;
                    }
                }

                $io->table(['', 'Action Name', 'Message'], $rows);

                if ($failureCount > 0) {
                    $io->error(sprintf('Finished deletion with %d success(es) and %d failure(s).', $successCount, $failureCount));

                    return Command::FAILURE;
                }

                $io->success(sprintf('All specified actions were successfully deleted from the "%s" module.', $moduleName));
            } else {
                $io->comment('Operation cancelled by user. No actions were deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Action Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
