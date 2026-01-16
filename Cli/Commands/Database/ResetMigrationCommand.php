<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to rollback all migrations.
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

class ResetMigrationCommand extends Command
{
    private function isProductionEnvironment(): bool
    {
        return Environment::isProduction();
    }

    protected function configure(): void
    {
        $this->setName('migration:reset')
            ->setDescription('Rolls back all executed migrations.')
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
            $io->error('Cannot run reset command in production without the --force flag.');

            return Command::FAILURE;
        }

        $io->title('Database Reset');
        $io->warning('This will roll back ALL migrations and drop the migrations table!');

        if (! $force && ! $io->confirm('Are you absolutely sure you want to proceed with the database reset?')) {
            $io->comment('Operation cancelled by user.');

            return Command::SUCCESS;
        }

        try {
            $results = $migrator->reset();

            if (empty($results)) {
                $io->comment('No migrations found to reset.');

                return Command::SUCCESS;
            }

            $migrateCommand = new MigrateDatabaseCommand();
            $migrateCommand->displayMigrationResults($io, $results, 'Rolled back');

            $io->success('Database successfully reset!');
        } catch (RuntimeException $e) {
            $io->error('Reset Failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
