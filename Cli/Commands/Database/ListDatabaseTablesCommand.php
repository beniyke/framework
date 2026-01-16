<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to list all tables in the database.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Database;

use Cli\Build\DBA;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class ListDatabaseTablesCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('database:tables')
            ->setDescription('Lists all tables in your database.')
            ->setHelp('This command list all tables in your database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $commandName = $this->getName();

        $io->title('Database Table List');
        $io->note(sprintf('Executing Command: %s', $commandName));

        try {
            $dba = resolve(DBA::class);
            $build = $dba->listDatabaseTables();

            if ($build['status']) {
                $io->success($build['message']);

                if (! empty($build['tables'])) {
                    $tables = $build['tables'];
                    $rows = [];

                    $io->section('Found Tables');

                    foreach ($tables as $tableEntry) {
                        $rows[] = [(string) $tableEntry['#'], (string) $tableEntry['name']];
                    }

                    $io->table(['#', 'Table Name'], $rows);
                } else {
                    $io->comment('No tables found in the database.');
                }
            } else {
                $io->error('Table Listing Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error('Fatal Error Listing Tables: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
