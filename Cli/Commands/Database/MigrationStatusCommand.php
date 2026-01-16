<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to show the status of each migration file.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Database;

use Database\ConnectionInterface;
use Database\Helpers\DatabaseOperationConfig;
use Database\Migration\Migrator;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationStatusCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('migration:status')
            ->setDescription('Shows the status of each migration file (ran or pending).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = resolve(ConnectionInterface::class);
        $config = resolve(DatabaseOperationConfig::class);
        $migrationPath = $config->getMigrationsPath();

        $io = new SymfonyStyle($input, $output);
        $migrator = new Migrator($connection, $migrationPath);

        $io->title('Migration Status');

        try {
            $statusData = $migrator->getStatus();

            if (empty($statusData)) {
                $io->comment('No migration files found in the path: ' . $migrationPath);

                return Command::SUCCESS;
            }

            $rows = [];
            $pendingCount = 0;

            foreach ($statusData as $file => $status) {
                if ($status === 'PENDING') {
                    $displayStatus = '<fg=red>PENDING</>';
                    $pendingCount++;
                } else {
                    $displayStatus = '<fg=green>RAN</>';
                }

                $rows[] = [
                    basename($file, '.php'),
                    $displayStatus,
                ];
            }

            $io->table(['Migration File', 'Status'], $rows);

            if ($pendingCount > 0) {
                $io->warning(sprintf('%d migration(s) pending.', $pendingCount));
            } else {
                $io->success('All migrations have been run.');
            }
        } catch (Exception $e) {
            $io->error('Status Check Failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
