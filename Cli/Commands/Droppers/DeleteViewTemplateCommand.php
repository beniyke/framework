<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete specific view templates.
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

class DeleteViewTemplateCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('templatename', InputArgument::REQUIRED, 'Name Of The View template(s) to be deleted (comma-separated).')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to delete The View template from.')
            ->setName('view:delete-template')
            ->setDescription('Deletes specific view template(s).')
            ->setHelp('This command allows you to delete a specified view template...');
    }

    protected function deleteViewTemplateConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=yellow>Are you sure you want to delete the specified View Template(s)? [y]/n </>', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $templateNamesInput = $input->getArgument('templatename');
        $moduleName = $input->getArgument('modulename');

        $io->title('View Template Deletion');
        $io->note(sprintf('Attempting to delete View Template(s) "%s" from module "%s".', $templateNamesInput, $moduleName));

        try {
            $question = $this->deleteViewTemplateConfirmation();

            if ($io->askQuestion($question)) {

                $dropper = Droppers::getInstance();
                $templates_name = explode(',', $templateNamesInput);

                $io->section('Deletion Results');

                $rows = [];
                $successCount = 0;
                $failureCount = 0;

                foreach ($templates_name as $template_name) {
                    $template_name = trim($template_name);

                    $io->text(sprintf('   Attempting to delete: <info>%s</info>', $template_name));

                    $build = $dropper->template(strtolower($template_name), $moduleName);

                    if ($build['status']) {
                        $rows[] = ['<info>✓</info>', $template_name, $build['message']];
                        $successCount++;
                    } else {
                        $rows[] = ['<error>✗</error>', $template_name, $build['message']];
                        $failureCount++;
                    }
                }

                $io->table(['', 'Template Name', 'Message'], $rows);

                if ($failureCount > 0) {
                    $io->error(sprintf('Finished deletion with %d success(es) and %d failure(s).', $successCount, $failureCount));

                    return Command::FAILURE;
                }

                $io->success(sprintf('All specified View Templates were successfully deleted from the "%s" module.', $moduleName));
            } else {
                $io->comment('Operation cancelled by user. No View Templates were deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during View Template Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
