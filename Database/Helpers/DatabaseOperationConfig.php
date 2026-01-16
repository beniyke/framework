<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Handles application configuration that is NOT directly related to building a PDO connection,
 * such as file paths for backups, migrations, and seeds.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Helpers;

use Helpers\File\Paths;

class DatabaseOperationConfig
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getBackupPath(): string
    {
        $backupPath = $this->config['operations']['backup']['path'] ?? 'App/storage/database/backup';

        return Paths::basePath($backupPath);
    }

    public function getBackupName(): string
    {
        return $this->config['operations']['backup']['name'] ?? 'backup';
    }

    public function getMigrationsPath(): string
    {
        $migrationsPath = $this->config['operations']['migrations'] ?? 'App/storage/database/migrations';

        return Paths::basePath($migrationsPath);
    }

    public function getSeedsPath(): string
    {
        $seedsPath = $this->config['operations']['seeds'] ?? 'App/storage/database/seeds';

        return Paths::basePath($seedsPath);
    }

    public function getSlowQueryThreshold(): int
    {
        return (int) ($this->config['logging']['slow_query_threshold'] ?? 500);
    }
}
