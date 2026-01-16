<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an existing module.
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

class DeleteModuleCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to be Deleted.')
            ->setName('module:delete')
            ->setDescription('Deletes Existing Module.')
            ->setHelp('This command allows you to delete an existing module...');
    }

    protected function deleteConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=red>DANGER: Are you absolutely sure you want to delete this module and all its contents? [y]/n </>', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $moduleToDelete = $input->getArgument('modulename');
        $commandName = $this->getName();

        $io->title('Module Deletion');
        $io->note(sprintf('Executing command: %s. Target module: "%s".', $commandName, $moduleToDelete));
        $io->block('FATAL WARNING! Deleting a module will recursively delete ALL Controllers, Models, Actions, and files within it. This action cannot be undone.', 'CRITICAL', 'fg=white;bg=red', ' ', true);

        try {
            $question = $this->deleteConfirmation();

            if ($io->askQuestion($question)) {
                $io->text('Deletion confirmed. Executing recursive module delete...');

                $dropper = Droppers::getInstance();

                $build = $dropper->path('App/src')->directory($moduleToDelete);

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->warning('Deletion Failed: ' . $build['message']);
                }
            } else {
                $io->comment('Operation cancelled by user. Module was not deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Module Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
