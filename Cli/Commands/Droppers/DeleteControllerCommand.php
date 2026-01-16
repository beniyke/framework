<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an existing controller.
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

class DeleteControllerCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('controllername', InputArgument::REQUIRED, 'Name Of the Controller(s) to be Deleted (comma-separated).')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Delete the Controller from.')
            ->setName('controller:delete')
            ->setDescription('Deletes Existing Controller(s).')
            ->setHelp('This command allows you to delete an existing Controller...' . PHP_EOL . 'Note: To delete a controller from a module, first enter the name of the controller, add a space, then the name of the module e.g. login account. You can delete multiple controllers by comma-separating their names.');
    }

    protected function deleteConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=yellow>Are you sure you want to delete the specified Controller(s)? [y]/n </>', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $controllerNamesInput = $input->getArgument('controllername');
        $moduleName = $input->getArgument('modulename');

        $io->title('Controller Deletion');
        $io->note(sprintf('Attempting to delete controller(s) "%s" from module "%s".', $controllerNamesInput, $moduleName));

        try {
            $question = $this->deleteConfirmation();

            if ($io->askQuestion($question)) {
                $dropper = Droppers::getInstance();
                $controllers_name = explode(',', $controllerNamesInput);

                $io->section('Deletion Results');

                $rows = [];
                $successCount = 0;
                $failureCount = 0;

                foreach ($controllers_name as $controller_name) {
                    $controller_name = trim($controller_name);
                    $io->text(sprintf('   Attempting to delete: <info>%s</info>', $controller_name));

                    $build = $dropper->controller(strtolower($controller_name), $moduleName);

                    if ($build['status']) {
                        $rows[] = ['<info>✓</info>', $controller_name, $build['message']];
                        $successCount++;
                    } else {
                        $rows[] = ['<error>✗</error>', $controller_name, $build['message']];
                        $failureCount++;
                    }
                }

                $io->table(['', 'Controller Name', 'Message'], $rows);

                if ($failureCount > 0) {
                    $io->error(sprintf('Finished deletion with %d success(es) and %d failure(s).', $successCount, $failureCount));

                    return Command::FAILURE;
                }

                $io->success(sprintf('All specified controllers were successfully deleted from the "%s" module.', $moduleName));
            } else {
                $io->comment('Operation cancelled by user. No controllers were deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Controller Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
