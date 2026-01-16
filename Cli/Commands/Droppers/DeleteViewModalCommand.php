<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete a specific view modal.
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

class DeleteViewModalCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('modalname', InputArgument::REQUIRED, 'Name Of The View Modal to be deleted.')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to delete The View Modal from.')
            ->setName('view:delete-modal')
            ->setDescription('Deletes specific view modal.')
            ->setHelp('This command allows you to delete a specified view modal...');
    }

    protected function deleteViewModalConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=yellow>Are you sure you want to delete this view modal file? [y]/n </>', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $modalToDelete = $input->getArgument('modalname');
        $moduleName = $input->getArgument('modulename');

        $io->title('View Modal Deletion');
        $io->note(sprintf('Attempting to delete modal "%s" from module "%s".', $modalToDelete, $moduleName));

        try {
            $question = $this->deleteViewModalConfirmation();

            if ($io->askQuestion($question)) {

                $io->text('Deletion confirmed. Executing...');

                $dropper = Droppers::getInstance();
                $build = $dropper->modal($modalToDelete, $moduleName);

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->warning('Deletion Failed: ' . $build['message']);
                }
            } else {
                $io->comment('Operation cancelled by user. View Modal was not deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during View Modal Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
