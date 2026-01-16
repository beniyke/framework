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

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateModelTrait
{
    public function model(string $model, string $module): array
    {
        $default_template = Paths::cliPath('Build/Templates/ModelTemplate.php.stub');
        $custom_template = Paths::storagePath('build/ModelTemplate.php.stub');

        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);
        $namespace = 'App\\' . $module_name;

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $model_name = ucfirst($model);
        $model_identifier = $model_name;
        $file = $directory . '/Models/' . $model_name . '.php';

        FileSystem::mkdir($directory . '/Models');

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $model_name . ' model build not successful, ' . $model_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{modelname}') === false) {
                return [
                    'status' => false,
                    'message' => 'Model template file not found.',
                ];
            }

            $newcontent = str_replace(['{namespace}', '{modelname}', '{inferredTableName}'], [$namespace, $model_name, strtolower($model_name)], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $model_name . ' model generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $model_name . ' model could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
