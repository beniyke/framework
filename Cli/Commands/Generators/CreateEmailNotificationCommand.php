<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new email notification.
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

class CreateEmailNotificationCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('email_notification_name', InputArgument::REQUIRED, 'Name Of The Email Notification to Generate.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Generate The Email Notification to.')
            ->setName('email-notification:create')
            ->setDescription('Creates new Email Notification.')
            ->setHelp('This command allows you to create a new Email Notification...' . PHP_EOL . 'Note: To create a email notification for a module, first enter the name of the email notification, add a space, then the name of the module e.g. signup auth');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $notificationName = $input->getArgument('email_notification_name');
        $moduleName = $input->getArgument('modulename');

        $io->title('Email Notification Generator');
        $io->note(sprintf(
            'Attempting to create Email Notification "%s"%s.',
            $notificationName,
            $moduleName ? ' in module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $generator = Generators::getInstance();

            $io->text('Generating email notification file...');

            $build = $generator->emailNotification($notificationName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $notificationName,
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
            $io->error('Fatal Error during Email Notification Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
