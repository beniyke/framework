<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class RunScheduleCommand implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Runners;

use Cron\Interfaces\CronInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class RunScheduleCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('schedule:run')
            ->setDescription('Run the scheduled tasks');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Task Scheduler');

        try {
            /** @var CronInterface $scheduler */
            $scheduler = resolve(CronInterface::class);
            $scheduler->run();

            $io->success('Scheduled tasks executed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error('Failed to run scheduled tasks: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
