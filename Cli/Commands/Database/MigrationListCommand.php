<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to list all migrations and their status.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Database;

use Database\ConnectionInterface;
use Database\Helpers\DatabaseOperationConfig;
use Database\Migration\Migrator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('migration:list')
            ->setDescription('Lists all available migrations with their run status and batch number.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = resolve(ConnectionInterface::class);
        $config = resolve(DatabaseOperationConfig::class);

        $io = new SymfonyStyle($input, $output);
        $migrator = new Migrator($connection, $config->getMigrationsPath());

        $io->title('Available Migrations Status');

        try {
            $fileStatuses = $migrator->getStatus();
            $ranData = $migrator->getRepository()->getMigratedFiles();

            $ranMap = [];
            foreach ($ranData as $item) {
                $ranMap[basename($item['migration'])] = $item['batch'];
            }

            $rows = [];

            foreach ($fileStatuses as $file => $status) {
                $baseFileName = basename($file);
                $batch = $ranMap[$baseFileName] ?? '--';

                if ($status === 'PENDING') {
                    $displayStatus = 'PENDING';
                } else {
                    $displayStatus = 'RAN';
                }

                $rows[] = [$baseFileName, $displayStatus, $batch];
            }

            usort($rows, fn ($a, $b) => strcmp($a[0], $b[0]));

            $io->table(['Migration File (Timestamp)', 'Status', 'Batch'], $rows);
        } catch (RuntimeException $e) {
            $io->error('Failed to list migrations. Ensure the migration repository table exists: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
