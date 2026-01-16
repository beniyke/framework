<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete existing services.
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

class DeleteServiceCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('service_name', InputArgument::REQUIRED, 'Name Of the Service(s) to be Deleted (comma-separated).')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Delete the Service from.')
            ->setName('service:delete')
            ->setDescription('Deletes Existing Service(s).')
            ->setHelp('This command allows you to delete an existing Service...' . PHP_EOL . 'Note: To delete a Service from a module, first enter the name of the Service(s), add a space, then the name of the module e.g. login account. Use commas to delete multiple services.');
    }

    protected function deleteConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=yellow>Are you sure you want to delete the specified Service(s)? [y]/n </>', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $serviceNamesInput = $input->getArgument('service_name');
        $moduleName = $input->getArgument('modulename');

        $io->title('Service Deletion');
        $io->note(sprintf('Attempting to delete service(s) "%s" from module "%s".', $serviceNamesInput, $moduleName));

        try {
            $question = $this->deleteConfirmation();

            if ($io->askQuestion($question)) {
                $dropper = Droppers::getInstance();
                $services_name = explode(',', $serviceNamesInput);

                $io->section('Deletion Results');

                $rows = [];
                $successCount = 0;
                $failureCount = 0;

                foreach ($services_name as $service_name) {
                    $service_name = trim($service_name);

                    $io->text(sprintf('   Attempting to delete: <info>%s</info>', $service_name));

                    $build = $dropper->service(strtolower($service_name), $moduleName);

                    if ($build['status']) {
                        $rows[] = ['<info>✓</info>', $service_name, $build['message']];
                        $successCount++;
                    } else {
                        $rows[] = ['<error>✗</error>', $service_name, $build['message']];
                        $failureCount++;
                    }
                }

                $io->table(['', 'Service Name', 'Message'], $rows);

                if ($failureCount > 0) {
                    $io->error(sprintf('Finished deletion with %d success(es) and %d failure(s).', $successCount, $failureCount));

                    return Command::FAILURE;
                }

                $io->success(sprintf('All specified services were successfully deleted from the "%s" module.', $moduleName));
            } else {
                $io->comment('Operation cancelled by user. No services were deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Service Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
