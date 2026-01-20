<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an existing email notification.
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

class DeleteEmailNotificationCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('email_notification_name', InputArgument::REQUIRED, 'Name Of the Email Notification(s) to be Deleted (comma-separated).')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Delete the Email Notification from.')
            ->setName('email-notification:delete')
            ->setDescription('Deletes Existing Email Notification(s).')
            ->setHelp('This command allows you to delete an existing Email Notification...' . PHP_EOL . 'Note: To delete a email notification from a module, first enter the name of the email notification(s), add a space, then the name of the module e.g signup auth. Use commas to delete multiple notifications.');
    }

    protected function deleteConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=yellow>Are you sure you want to delete the specified Email Notification(s)? [y]/n </>', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $notificationNamesInput = $input->getArgument('email_notification_name');
        $moduleName = $input->getArgument('modulename');

        $io->title('Email Notification Deletion');
        $io->note(sprintf(
            'Attempting to delete notification(s) "%s"%s.',
            $notificationNamesInput,
            $moduleName ? ' from module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $question = $this->deleteConfirmation();

            if ($io->askQuestion($question)) {

                $dropper = Droppers::getInstance();
                $notifications_name = explode(',', $notificationNamesInput);

                $io->section('Deletion Results');

                $rows = [];
                $successCount = 0;
                $failureCount = 0;

                foreach ($notifications_name as $email_notification_name) {
                    $email_notification_name = trim($email_notification_name);

                    $io->text(sprintf('   Attempting to delete: %s', $email_notification_name));

                    $build = $dropper->emailNotification(strtolower($email_notification_name), $moduleName);

                    if ($build['status']) {
                        $rows[] = ['✓', $email_notification_name, $build['message']];
                        $successCount++;
                    } else {
                        $rows[] = ['✗', $email_notification_name, $build['message']];
                        $failureCount++;
                    }
                }

                $io->table(['', 'Notification Name', 'Message'], $rows);

                if ($failureCount > 0) {
                    $io->error(sprintf('Finished deletion with %d success(es) and %d failure(s).', $successCount, $failureCount));

                    return Command::FAILURE;
                }

                $io->success(sprintf(
                    'All specified email notifications were successfully deleted%s.',
                    $moduleName ? ' from the "' . $moduleName . '" module' : ' (global)'
                ));
            } else {
                $io->comment('Operation cancelled by user. No email notifications were deleted.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Notification Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
