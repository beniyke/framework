<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete a custom command.
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

class DeleteCommandCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('commandname', InputArgument::REQUIRED, 'Name of the command to delete')
            ->setName('command:delete')
            ->setDescription('Deletes a command.')
            ->setHelp('This command allows you to delete a custom command');
    }

    protected function deleteCommandConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('Are you sure you want to delete this command?', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $commandToDelete = $input->getArgument('commandname');

        $io->title('Command Deletion');
        $io->note(sprintf('Attempting to delete command: "%s".', $commandToDelete));
        $io->block('WARNING: This action will permanently remove the command file.', 'WARNING', 'fg=black;bg=yellow', ' ', true);

        try {
            $question = $this->deleteCommandConfirmation();

            if ($io->askQuestion($question)) {
                $io->text('Deletion confirmed. Executing...');

                $dropper = Droppers::getInstance();
                $build = $dropper->command($commandToDelete);

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->warning('Deletion Failed: ' . $build['message']);
                }
            } else {
                $io->comment('Operation cancelled by user. Command file was not deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Command Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
