<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class CreateScheduleCommand implementation.
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

class CreateScheduleCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('schedule:create')
            ->setDescription('Creates a new schedule class.')
            ->addArgument('name', InputArgument::REQUIRED, 'Name of the schedule (e.g., Backup)')
            ->addArgument('module', InputArgument::OPTIONAL, 'The module name (e.g., Account) if creating in App/src/{Module}')
            ->setHelp('This command allows you to create a new schedulable class in App/Schedules or App/src/{Module}/Schedules.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $module = $input->getArgument('module');

        $io->title('Schedule Generator');
        $io->note(sprintf(
            'Attempting to create schedule: "%s"%s.',
            $name,
            $module ? " in module \"{$module}\"" : " in App root"
        ));

        try {
            $generator = Generators::getInstance();
            $build = $generator->schedule($name, $module);

            if ($build['status']) {
                $io->success($build['message']);
                if (isset($build['path'])) {
                    $io->text("File location: {$build['path']}");
                }
            } else {
                $io->error('Generation Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
