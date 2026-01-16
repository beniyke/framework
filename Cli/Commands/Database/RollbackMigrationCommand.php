<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to rollback the last migration batch.
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

class RollbackMigrationCommand extends Command
{
    private function isProductionEnvironment(): bool
    {
        return Environment::isProduction();
    }

    protected function configure(): void
    {
        $this->setName('migration:rollback')
            ->setDescription('Rolls back the last batch of migrations, or a specific number of steps.')
            ->addOption('step', 's', InputOption::VALUE_REQUIRED, 'The number of migrations to rollback.', null)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Allows the command to run in a production environment.')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Rollback specific migration file(s). Comma-separated for multiple files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = resolve(ConnectionInterface::class);
        $config = resolve(DatabaseOperationConfig::class);
        $io = new SymfonyStyle($input, $output);
        $migrator = new Migrator($connection, $config->getMigrationsPath());

        $steps = $input->getOption('step') ? (int) $input->getOption('step') : 0;
        $force = $input->getOption('force');
        $fileOption = $input->getOption('file');
        $isProduction = $this->isProductionEnvironment();

        if ($isProduction && ! $force) {
            $io->error('Cannot run rollback command in production without the --force flag.');

            return Command::FAILURE;
        }

        $io->title('Rolling Back Database Migrations');

        if ($isProduction && ! $force && ! $io->confirm('WARNING: Are you sure you want to rollback the migrations?')) {
            $io->comment('Operation cancelled by user.');

            return Command::SUCCESS;
        }

        try {
            // Rollback specific files if --file option is provided
            if ($fileOption) {
                $files = array_map('trim', explode(',', $fileOption));
                $results = [];

                foreach ($files as $file) {
                    $results[] = $migrator->rollbackFile($file);
                }

                if (empty($results)) {
                    $io->comment('No migrations to rollback.');

                    return Command::SUCCESS;
                }
            } elseif ($steps > 0) {
                $results = $migrator->rollbackSteps($steps);
                $io->comment(sprintf('Rolling back %d step(s)...', $steps));
            } else {
                $results = $migrator->rollback();
                $io->comment('Rolling back the last batch...');
            }

            if (empty($results)) {
                $io->comment('Nothing to rollback.');

                return Command::SUCCESS;
            }

            $migrateCommand = new MigrateDatabaseCommand();
            $migrateCommand->displayMigrationResults($io, $results, 'Rolled back');

            $io->success('Rollback successful!');
        } catch (RuntimeException $e) {
            $io->error('Rollback Failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
