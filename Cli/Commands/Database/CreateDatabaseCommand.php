<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new database.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Database;

use Cli\Build\DBA;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class CreateDatabaseCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('database:create')
            ->setDescription('Creates a new database.')
            ->addArgument('databasename', InputArgument::OPTIONAL, 'Name Of The Database to Create.')
            ->setHelp('This command allows you to create a new database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dbName = $input->getArgument('databasename');
        $commandName = $this->getName();

        $io->title('Creating Database');
        $io->note(sprintf('Executing Command: %s %s', $commandName, $dbName ?? '(Default/Config Name)'));

        try {
            $dba = resolve(DBA::class);
            $io->progressStart(1);

            $build = $dba->createDatabase($dbName);

            $io->progressFinish();

            if ($build['status']) {
                $io->success($build['message']);
            } else {
                $io->warning('Operation Status: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error('Database Creation Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
