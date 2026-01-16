<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new migration file.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Database;

use Database\Helpers\DatabaseOperationConfig;
use Helpers\File\FileSystem;
use Helpers\File\Paths;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateMigrationCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('migration:create')
            ->setDescription('Creates a new migration file.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the migration (e.g., CreateUsersTable or create_users_table).')
            ->addOption('create', 'c', InputOption::VALUE_OPTIONAL, 'The table to be created. Forces the create stub.', null)
            ->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'The table to be altered. Forces the table stub.', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = resolve(DatabaseOperationConfig::class);
        $migrationPath = $config->getMigrationsPath();

        $io = new SymfonyStyle($input, $output);

        $rawName = $input->getArgument('name');
        $createTable = $input->getOption('create');
        $alterTable = $input->getOption('table');

        $io->title('Creating Migration File');

        try {
            [$className, $fileNameBase] = $this->normalizeNames($rawName);
            [$templateName, $tableName] = $this->determineTemplateAndTable($fileNameBase, $createTable, $alterTable);

            $timestamp = date('Y_m_d_His');
            $fileName = "{$timestamp}_{$fileNameBase}.php";
            $filePath = "{$migrationPath}/{$fileName}";

            FileSystem::mkdir($migrationPath, 0755, true);

            $template = $this->getStubContent($templateName);

            if (! $template) {
                $io->error('Migration template file not found.');

                return Command::FAILURE;
            }

            $content = str_replace(['{{ class }}', '{{ table }}'], [$className, $tableName], $template);

            FileSystem::put($filePath, $content);

            $io->success("Migration created successfully: {$fileName}");
        } catch (RuntimeException $e) {
            $io->error('Failed to create migration file: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function normalizeNames(string $rawName): array
    {
        if (preg_match('/^[A-Z][a-zA-Z0-9]*$/', $rawName)) {
            $fileNameBase = strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $rawName));
            $className = $rawName;
        } else {
            $fileNameBase = strtolower($rawName);
            $className = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $fileNameBase)));
        }

        return [$className, $fileNameBase];
    }

    protected function determineTemplateAndTable(string $fileNameBase, ?string $createTable, ?string $alterTable): array
    {
        $templateName = 'MigrationTemplate.php.stub';
        $tableName = 'null';

        if ($createTable !== null) {
            if ($alterTable !== null) {
                throw new RuntimeException('Cannot use both --create and --table options simultaneously.');
            }
            $templateName = 'MigrationCreateTemplate.php.stub';
            $tableName = $createTable ?: $this->extractTableNameFromCreateName($fileNameBase);
        } elseif ($alterTable !== null) {
            $templateName = 'MigrationTableTemplate.php.stub';
            $tableName = $alterTable;
        }

        return [$templateName, $tableName];
    }

    protected function extractTableNameFromCreateName(string $name): string
    {
        if (preg_match('/^create_(\w+)_table$/', $name, $matches)) {
            return $matches[1];
        }

        return 'null';
    }

    protected function getStubContent(string $templateName): ?string
    {
        $templatePath = Paths::cliPath('Build/Templates/' . $templateName);

        if (! FileSystem::exists($templatePath)) {
            return null;
        }

        return FileSystem::get($templatePath);
    }
}
