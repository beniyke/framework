<?php

declare(strict_types=1);

namespace Cli\Commands\Generators;

use Cli\Build\Generators;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteScheduleCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('schedule:delete')
            ->setDescription('Deletes a schedule class.')
            ->addArgument('name', InputArgument::REQUIRED, 'Name of the schedule to delete')
            ->addArgument('module', InputArgument::OPTIONAL, 'The module name if it exists in App/src/{Module}')
            ->setHelp('This command allows you to delete an existing schedule class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $module = $input->getArgument('module');

        $io->title('Schedule Deletion');

        if (!$io->confirm("Are you sure you want to delete the schedule \"{$name}\"?")) {
            return self::SUCCESS;
        }

        try {
            $generator = Generators::getInstance();
            $build = $generator->deleteSchedule($name, $module);

            if ($build['status']) {
                $io->success($build['message']);
            } else {
                $io->error('Deletion Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
