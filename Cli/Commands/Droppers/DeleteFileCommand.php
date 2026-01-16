<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete a specific file.
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

class DeleteFileCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('filename', InputArgument::REQUIRED, 'Name of the file to be deleted.')
            ->setName('file:delete')
            ->setDescription('Deletes specific file.')
            ->setHelp('This command allows you to delete a specified file...');
    }

    protected function deleteFileConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=yellow>Are you sure you want to delete this file? [y]/n </>', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fileToDelete = $input->getArgument('filename');

        $io->title('File Deletion');
        $io->note(sprintf('Attempting to delete file: "%s".', $fileToDelete));
        $io->block('WARNING: This action will permanently remove the file. This cannot be undone.', 'WARNING', 'fg=black;bg=yellow', ' ', true);

        try {
            $question = $this->deleteFileConfirmation();

            if ($io->askQuestion($question)) {
                $io->text('Deletion confirmed. Executing...');

                $dropper = Droppers::getInstance();

                $build = $dropper->path('App')
                    ->file(ucfirst($fileToDelete));

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->warning('Deletion Failed: ' . $build['message']);
                }
            } else {
                $io->comment('Operation cancelled by user. File was not deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during File Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
