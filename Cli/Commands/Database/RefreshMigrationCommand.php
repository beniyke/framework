<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to reset and re-run all migrations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Database;

use Core\Support\Environment;
use Database\ConnectionInterface;
use Database\Helpers\DatabaseOperationConfig;
use Database\Migration\Migrator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RefreshMigrationCommand extends Command
{
    private function isProductionEnvironment(): bool
    {
        return Environment::isProduction();
    }

    protected function configure(): void
    {
        $this->setName('migration:refresh')
            ->setDescription('Resets the database and reruns all migrations.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Allows the command to run in a production environment.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = resolve(ConnectionInterface::class);
        $config = resolve(DatabaseOperationConfig::class);

        $io = new SymfonyStyle($input, $output);
        $migrator = new Migrator($connection, $config->getMigrationsPath());

        $force = $input->getOption('force');
        $isProduction = $this->isProductionEnvironment();

        if ($isProduction && ! $force) {
            $io->error('Cannot run refresh command in production without the --force flag.');

            return Command::FAILURE;
        }

        $io->title('Database Refresh (Reset & Rerun)');
        $io->warning('This will roll back ALL migrations and rerun them, potentially resulting in data loss.');

        if (! $force && ! $io->confirm('Are you absolutely sure you want to refresh the database? This is a destructive action.')) {
            $io->comment('Operation cancelled by user.');

            return Command::SUCCESS;
        }

        try {
            $results = $migrator->refresh();
            $migrateCommand = new MigrateDatabaseCommand();

            if (! empty($results['rolledBack'])) {
                $io->section('Rollback Phase');
                $migrateCommand->displayMigrationResults($io, $results['rolledBack'], 'Rolled back');
            } else {
                $io->comment('No migrations to roll back.');
            }

            if (! empty($results['migrated'])) {
                $io->section('Migration Phase');
                $migrateCommand->displayMigrationResults($io, $results['migrated'], 'Migrated');
            }

            $io->success('Database refresh complete!');
        } catch (RuntimeException $e) {
            $io->error('Refresh Failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
