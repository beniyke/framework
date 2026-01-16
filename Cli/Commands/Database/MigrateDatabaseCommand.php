<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to run pending database migrations.
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

class MigrateDatabaseCommand extends Command
{
    private function isProductionEnvironment(): bool
    {
        return Environment::isProduction();
    }

    protected function configure(): void
    {
        $this->setName('migration:run')
            ->setDescription('Runs all pending database migrations.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Allows the command to run in a production environment.')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Run specific migration file(s). Comma-separated for multiple files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = resolve(ConnectionInterface::class);
        $config = resolve(DatabaseOperationConfig::class);

        $io = new SymfonyStyle($input, $output);
        $migrator = new Migrator($connection, $config->getMigrationsPath());

        $force = $input->getOption('force');
        $fileOption = $input->getOption('file');
        $isProduction = $this->isProductionEnvironment();

        if ($isProduction && ! $force) {
            $io->error('Cannot run migrations in production without the --force flag.');

            return Command::FAILURE;
        }

        $io->title('Running Database Migrations');

        try {
            // Run specific files if --file option is provided
            if ($fileOption) {
                $files = array_map('trim', explode(',', $fileOption));
                $results = $migrator->runFiles($files);

                if (empty($results)) {
                    $io->comment('No migrations to run. Files may already be migrated or not found.');

                    return Command::SUCCESS;
                }
            } else {
                // Run all pending migrations
                $results = $migrator->run();

                if (empty($results)) {
                    $io->comment('Nothing to migrate.');

                    return Command::SUCCESS;
                }
            }

            $this->displayMigrationResults($io, $results, 'Migrated');
            $io->success('Migration complete!');
        } catch (RuntimeException $e) {
            $io->error('Migration Failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    public function displayMigrationResults(SymfonyStyle $io, array $results, string $action): void
    {
        $rows = [];
        $totalTime = 0;

        foreach ($results as $result) {
            $rows[] = [
                ($action === 'Migrated' ? '<info>✓</info>' : '<comment>✗</comment>'),
                basename($result['file'], '.php'),
                $result['time'] . 's',
            ];
            $totalTime += $result['time'];
        }

        $io->table(['', $action, 'Time'], $rows);
        $io->note(sprintf('Total execution time: %s seconds.', round($totalTime, 2)));
    }
}
