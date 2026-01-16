<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete a specific directory.
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

class DeleteDirectoryCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('directoryname', InputArgument::REQUIRED, 'Name of the directory to be deleted.')
            ->setName('directory:delete')
            ->setDescription('Deletes specific directory.')
            ->setHelp('This command allows you to delete a specified directory...');
    }

    protected function deleteDirectoryConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=red>DANGER: Are you absolutely sure you want to delete this directory and all its contents? [y]/n </>', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $directoryToDelete = $input->getArgument('directoryname');

        $io->title('Directory Deletion');
        $io->note(sprintf('Attempting to delete directory: "%s".', $directoryToDelete));
        $io->block('FATAL WARNING! This will recursively delete the directory and ALL files within it. This action cannot be undone.', 'CRITICAL', 'fg=white;bg=red', ' ', true);

        try {
            $question = $this->deleteDirectoryConfirmation();

            if ($io->askQuestion($question)) {

                $io->text('Deletion confirmed. Executing recursive delete...');

                $dropper = Droppers::getInstance();

                $build = $dropper->path('App')
                    ->directory(ucfirst($directoryToDelete));

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->warning('Deletion Failed: ' . $build['message']);
                }
            } else {
                $io->comment('Operation cancelled by user. Directory was not deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Directory Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
