<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new InApp Notification class.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Generators;

use Cli\Build\Generators;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateInAppNotificationCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('inapp_notification_name', InputArgument::REQUIRED, 'Name Of The InApp Notification Class to Generate.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Generate The InApp Notification to.')
            ->setName('inapp-notification:create')
            ->setDescription('Creates new InApp Notification class.')
            ->setHelp('This command allows you to create a new InApp Notification class...' . PHP_EOL . 'Note: To create an InApp notification for a module, first enter the name of the notification, add a space, then the name of the module e.g. SignupNotification Auth');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $notificationName = $input->getArgument('inapp_notification_name');
        $moduleName = $input->getArgument('modulename');

        $io->title('InApp Notification Generator');
        $io->note(sprintf(
            'Attempting to create InApp Notification "%s"%s.',
            $notificationName,
            $moduleName ? ' in module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $generator = Generators::getInstance();

            $io->text('Generating InApp notification class file...');

            $build = $generator->customNotification($notificationName, 'inApp', $moduleName);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Notification Class' => $notificationName,
                    'Type' => 'InApp',
                    'Module' => $moduleName ?: '(Global)',
                ];

                if (isset($build['path'])) {
                    $details['File Path'] = $build['path'];
                }

                $io->definitionList($details);
            } else {
                $io->error('Generation Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during InApp Notification Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
