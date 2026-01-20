<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to export a database or a specific table.
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

class ExportDatabaseCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('tablename', InputArgument::OPTIONAL, 'Name Of The Database Table to Export.')
            ->setName('database:export')
            ->setDescription('Exports a database or a database table.')
            ->setHelp('This command allows you to export a database or a database table.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tableName = $input->getArgument('tablename');
        $commandName = $this->getName();

        $target = $tableName ?: 'entire database';

        $io->title('Database Export');
        $io->note(sprintf('Attempting to export: %s', $target));

        try {
            $dba = resolve(DBA::class);
            $build = $dba->exportDatabase($tableName);

            if ($build['status']) {
                $io->success($build['message']);

                if (isset($build['filepath'])) {
                    $io->text(sprintf('Exported to: %s', $build['filepath']));
                }
            } else {
                $io->warning('Export Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error('Fatal Export Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
