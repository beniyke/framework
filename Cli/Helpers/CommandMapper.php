<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Helper for mapping console commands and dependencies.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Helpers;

class CommandMapper
{
    private const BASE_DB_DEPENDENT_COMMANDS = ['worker', 'start', 'restart', 'migration:run', 'migration:refresh', 'migration:reset', 'migration:rollback', 'migration:list', 'migration:lock', 'migration:unlock', 'migration:status', 'seeder:run', 'database:delete', 'database:export', 'database:import', 'database:tables', 'database:truncate', 'playground', 'package:uninstall', 'package:install', 'dev'];

    private const COMMAND_ALIASES = ['dock' => 'list'];
    private const TEST_COMMAND = ['vendor/bin/pest', 'test', 'vendor/bin/phpunit'];

    private array $dbDependentCommands;

    public function __construct(array $externalDbCommands = [])
    {
        $this->dbDependentCommands = array_merge(self::BASE_DB_DEPENDENT_COMMANDS, $externalDbCommands);
        $this->dbDependentCommands = array_unique(array_merge($this->dbDependentCommands, self::TEST_COMMAND));
    }

    public function resolveCommandName(string $commandName): string
    {
        return self::COMMAND_ALIASES[$commandName] ?? $commandName;
    }

    public function requiresDatabaseConnection(string $commandName): bool
    {
        if (env('APP_ENV') === 'testing') {
            return true;
        }

        $resolvedName = $this->resolveCommandName($commandName);

        return in_array($resolvedName, $this->dbDependentCommands);
    }

    public function isRunningTest(string $commandName): bool
    {
        if (env('APP_ENV') === 'testing') {
            return true;
        }

        $resolvedName = $this->resolveCommandName($commandName);

        return in_array($resolvedName, self::TEST_COMMAND);
    }
}
