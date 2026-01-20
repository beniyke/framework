<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Service for checking development environment readiness.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Services;

use Database\DB;
use Exception;
use Helpers\File\Paths;
use Queue\Providers\QueueServiceProvider;
use Symfony\Component\Console\Style\SymfonyStyle;

class DevReadinessService
{
    private const QUEUE_TABLE = 'queued_job';

    public function runChecks(SymfonyStyle $io): bool
    {
        $io->section('Checking Development Environment Readiness...');

        $checks = [
            'Database Connection' => fn () => $this->checkDatabaseConnection(),
            'Queue Service Provider' => fn () => $this->checkQueueProviderRegistered(),
            'Queue Jobs Table' => fn () => $this->checkQueueTableExists()
        ];

        $failures = [];

        foreach ($checks as $name => $check) {
            $io->text("Checking {$name}...");

            try {
                if ($check()) {
                    $io->writeln(" <fg=green>✓ Passed</>");
                } else {
                    $io->writeln(" <fg=red>✗ Failed</>");
                    $failures[$name] = true;
                }
            } catch (Exception $e) {
                $io->writeln(" <fg=red>✗ Error: {$e->getMessage()}</>");
                $failures[$name] = true;
            }
        }

        if (!empty($failures)) {
            $io->error('Development environment checks failed. Please resolve the issues below:');

            if (isset($failures['Queue Service Provider'])) {
                $io->text([
                    '<fg=yellow>Queue has not been installed.</>',
                    'To install, run this command:',
                    '<fg=cyan>php dock package:install Queue --system</>',
                    ''
                ]);
            }

            if (isset($failures['Queue Jobs Table']) && ! isset($failures['Queue Service Provider'])) {
                $io->text([
                    '<fg=yellow>The queue jobs table is missing.</>',
                    'Action: Run database migrations to create the required table:',
                    '<fg=cyan>php dock migrate</>',
                    ''
                ]);
            }

            if (isset($failures['Database Connection'])) {
                $io->text([
                    '<fg=yellow>Database connection failed.</>',
                    'Action: Check your .env file and ensure database credentials are correct.',
                    ''
                ]);
            }
        } else {
            $io->success('All readiness checks passed!');
        }

        return empty($failures);
    }

    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkQueueProviderRegistered(): bool
    {
        $providers = require Paths::basePath('App/Config/providers.php');

        return in_array(QueueServiceProvider::class, $providers);
    }

    private function checkQueueTableExists(): bool
    {
        $tables = DB::connection()->getTables();

        return ! empty(array_filter($tables, fn ($table) => strtolower($table) === strtolower(self::QUEUE_TABLE)));
    }
}
