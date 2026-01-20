<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating View Model components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\View;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateViewModelTrait
{
    public function view_model(string $view_model, ?string $module = null, bool $isForm = false): array
    {
        $default_template = Paths::cliPath('Build/Templates/ViewModelTemplate.php.stub');
        $custom_template = Paths::storagePath('build/ViewModelTemplate.php.stub');

        if ($isForm) {
            $default_template = Paths::cliPath('Build/Templates/FormViewModelTemplate.php.stub');
            $custom_template = Paths::storagePath('build/FormViewModelTemplate.php.stub');
        }

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

        $view_model_name = ucfirst($view_model);
        $file = $directory . '/Views/Models/' . $view_model_name . 'ViewModel.php';

        FileSystem::mkdir($directory . '/Views/Models');

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $view_model_name . ' view model build not successful, ' . $view_model_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{modelname}') === false) {
                return [
                    'status' => false,
                    'message' => 'request template file not found.',
                ];
            }

            $newcontent = str_replace(['{namespace}', '{modelname}'], [$namespace, $view_model_name . 'ViewModel'], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $view_model_name . ' view model generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $view_model_name . ' view model could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
