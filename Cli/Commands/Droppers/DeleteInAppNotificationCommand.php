<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an existing InApp Notification class.
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
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteInAppNotificationCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('inapp_notification_name', InputArgument::REQUIRED, 'Name(s) of the InApp Notification(s) to be Deleted (comma-separated).')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Delete the InApp Notification from.')
            ->setName('inapp-notification:delete')
            ->setDescription('Deletes Existing InApp Notification class(es).')
            ->setHelp('This command allows you to delete an existing InApp Notification class. Use a comma-separated list to delete multiple notifications. Example: `signup,welcome auth`');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $notificationNamesInput = $input->getArgument('inapp_notification_name');
        $moduleName = $input->getArgument('modulename');

        $io->title('InApp Notification Dropper');
        $io->note(sprintf(
            'Targeting notification(s): "%s"%s',
            $notificationNamesInput,
            $moduleName ? ' in module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $question = sprintf(
                'Are you absolutely sure you want to delete "%s"%s?',
                $notificationNamesInput,
                $moduleName ? ' from module "' . $moduleName . '"' : ''
            );

            if (! $io->confirm($question, false)) {
                $io->comment('Deletion cancelled by user.');

                return self::SUCCESS;
            }

            $dropper = Droppers::getInstance();
            $notificationNames = explode(',', $notificationNamesInput);

            $io->section('Initiating Deletion');

            foreach ($notificationNames as $notificationName) {
                $notificationName = trim($notificationName);

                if (empty($notificationName)) {
                    continue;
                }

                $io->text(sprintf('Attempting to delete: %s...', $notificationName));

                $build = $dropper->customNotification(strtolower($notificationName), 'inApp', $moduleName);

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->error($build['message']);
                }
            }

            $io->success('All deletion attempts completed.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Notification Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
