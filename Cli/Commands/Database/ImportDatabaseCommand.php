<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to import a database or a table from a file.
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

class ImportDatabaseCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('filename', InputArgument::REQUIRED, 'Name Of The Database File To Be Imported.')
            ->setName('database:import')
            ->setDescription('Imports a database or a database table.')
            ->setHelp('This command allows you to import a database or a database table.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fileName = $input->getArgument('filename');
        $commandName = $this->getName();

        $io->title('Database Import');
        $io->note(sprintf('Executing command: %s. Importing file: %s', $commandName, $fileName));

        try {
            $dba = resolve(DBA::class);
            $io->text('Starting import process...');

            $build = $dba->importDatabase($fileName);

            if ($build['status']) {
                $io->success($build['message']);
            } else {
                $io->warning('Import Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error('Fatal Import Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
