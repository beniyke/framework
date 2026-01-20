<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an existing task.
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

class DeleteTaskCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('taskname', InputArgument::REQUIRED, 'Name Of The Task to be Deleted.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Delete Task From.')
            ->setName('task:delete')
            ->setDescription('Deletes existing Task.')
            ->setHelp('This command allows you to delete an existing Task.' . PHP_EOL . 'Note: To delete a Task from a module, specify the module name e.g. "login Account". Omitting the module will attempt to delete a global task from "App/Tasks".');
    }

    protected function deleteConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('Are you sure you want to delete this task file?', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $taskToDelete = $input->getArgument('taskname');
        $moduleName = $input->getArgument('modulename');

        $io->title('Task Deletion');
        $io->note(sprintf(
            'Attempting to delete task "%s"%s.',
            $taskToDelete,
            $moduleName ? ' from module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $question = $this->deleteConfirmation();

            if ($io->askQuestion($question)) {

                $io->text('Deletion confirmed. Executing...');

                $dropper = Droppers::getInstance();
                $build = $dropper->task($taskToDelete, $moduleName);

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->warning('Deletion Failed: ' . $build['message']);
                }
            } else {
                $io->comment('Operation cancelled by user. Task was not deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Task Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
