<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Model components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Model;

use Cli\Build\MigrationParser;
use Cli\Build\SmartModelBuilder;
use Database\Helpers\DatabaseOperationConfig;
use Helpers\File\FileSystem;
use Helpers\File\Paths;
use Helpers\String\Str;
use Throwable;

trait GenerateModelTrait
{
    public function model(string $model, ?string $module = null, ?string $migrationFile = null): array
    {
        if ($module) {
            $module_name = ucfirst($module);
            $directory = Paths::appSourcePath($module_name);
            $namespace = 'App\\' . $module_name;

            if (! FileSystem::exists($directory)) {
                return [
                    'status' => false,
                    'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
                ];
            }
        } else {
            $directory = Paths::appPath();
            $namespace = 'App';
        }

        $model_name = ucfirst($model);
        $file = $directory . '/Models/' . $model_name . '.php';

        FileSystem::mkdir($directory . '/Models');

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $model_name . ' model build not successful, ' . $model_name . ' already exist.',
            ];
        }

        if ($migrationFile) {
            return $this->generateFromMigration($file, $namespace, $model_name, $migrationFile);
        }

        return $this->generateFromTemplate($file, $namespace, $model_name);
    }

    private function generateFromTemplate(string $file, string $namespace, string $modelName): array
    {
        $default_template = Paths::cliPath('Build/Templates/ModelTemplate.php.stub');
        $custom_template = Paths::storagePath('build/ModelTemplate.php.stub');
        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (! FileSystem::exists($templatefile)) {
            return ['status' => false, 'message' => 'Model template file not found.'];
        }

        $template = FileSystem::get($templatefile);

        if (strpos($template, '{modelname}') === false) {
            return ['status' => false, 'message' => 'Model template file not found.'];
        }

        $content = str_replace(
            ['{namespace}', '{modelname}', '{inferredTableName}'],
            [$namespace, $modelName, strtolower($modelName)],
            $template
        );

        if (FileSystem::put($file, $content)) {
            return ['status' => true, 'message' => $modelName . ' model generated successfully.'];
        }

        return ['status' => false, 'message' => $modelName . ' model could not be generated.'];
    }

    private function generateFromMigration(string $file, string $namespace, string $modelName, string $migrationFile): array
    {
        $migrationPath = $this->resolveMigrationPath($migrationFile);

        if (! $migrationPath) {
            return ['status' => false, 'message' => "Migration file '{$migrationFile}' not found."];
        }

        $templatePath = Paths::cliPath('Build/Templates/SmartModelTemplate.php.stub');

        if (! FileSystem::exists($templatePath)) {
            return ['status' => false, 'message' => 'Smart model template file not found.'];
        }

        $parsed = MigrationParser::parse($migrationPath);
        $tableName = $parsed['tableName'] ?? strtolower($modelName);

        $builder = new SmartModelBuilder($parsed);
        $replacements = $builder->buildReplacements($namespace, $modelName, $tableName);

        $template = FileSystem::get($templatePath);
        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        if (FileSystem::put($file, $content)) {
            return [
                'status' => true,
                'message' => $modelName . ' model generated from migration successfully.',
            ];
        }

        return ['status' => false, 'message' => $modelName . ' model could not be generated.'];
    }

    private function resolveMigrationPath(string $migrationFile): ?string
    {
        $migrationFile = str_replace('.php', '', $migrationFile);

        try {
            $config = resolve(DatabaseOperationConfig::class);
            $migrationsDir = $config->getMigrationsPath();
        } catch (Throwable) {
            $migrationsDir = Paths::basePath('App/storage/database/migrations');
        }

        if (! FileSystem::isDir($migrationsDir)) {
            return null;
        }

        $exactPath = $migrationsDir . '/' . $migrationFile . '.php';
        if (FileSystem::exists($exactPath)) {
            return $exactPath;
        }

        $snakeName = Str::snake($migrationFile);
        $files = FileSystem::scandir($migrationsDir);

        foreach ($files as $f) {
            if (str_contains($f, $migrationFile) || str_contains($f, $snakeName)) {
                return $migrationsDir . '/' . $f;
            }
        }

        return null;
    }
}
