<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete a provider.
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

class DeleteProviderCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('providername', InputArgument::REQUIRED, 'Name of the provider to delete')
            ->setName('provider:delete')
            ->setDescription('Deletes a provider.')
            ->setHelp('This command allows you to delete a provider');
    }

    protected function deleteProviderConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=yellow>Are you sure you want to delete this provider file? [y]/n </>', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $providerToDelete = $input->getArgument('providername');

        $io->title('Provider Deletion');
        $io->note(sprintf('Attempting to delete provider file: "%s".', $providerToDelete));

        try {
            $question = $this->deleteProviderConfirmation();

            if ($io->askQuestion($question)) {
                $io->text('Deletion confirmed. Executing...');

                $dropper = Droppers::getInstance();
                $build = $dropper->provider($providerToDelete);

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->warning('Deletion Failed: ' . $build['message']);
                }
            } else {
                $io->comment('Operation cancelled by user. Provider file was not deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Provider Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
