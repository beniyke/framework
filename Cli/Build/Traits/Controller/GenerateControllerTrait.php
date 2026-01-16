<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Controller components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Controller;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateControllerTrait
{
    public function controller(string $controller, string $module, bool $is_api = false): array
    {
        $default_template_path = Paths::cliPath('Build/Templates/');
        $custom_template_path = Paths::storagePath('build/templates/');
        $template_type = $is_api ? 'api' : 'default';

        $template = [
            'api' => 'ApiControllerTemplate.php.stub',
            'default' => 'ControllerTemplate.php.stub',
        ];

        $list_template = 'list-' . strtolower(plural($controller));
        $create_template = 'create-' . strtolower($controller);
        $edit_template = 'edit-' . strtolower($controller);
        $delete_template = 'delete-' . strtolower($controller);

        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);
        $namespace = "App\\$module_name";

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $custom_template = $custom_template_path . $template[$template_type];
        $default_template = $default_template_path . $template[$template_type];
        $controller_name = ucfirst($controller) . 'Controller';
        $controller_identifier = ucfirst($controller);
        $file = $directory . '/Controllers/' . $controller_name . '.php';
        $request_validation_name = $controller_identifier . ucfirst($template_type == 'api' ? 'Api' : 'Form') . 'RequestValidation';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $controller_name . ' controller build not successful, ' . $controller_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{controllername}') === false) {
                return [
                    'status' => false,
                    'message' => 'Controller template file not found.',
                ];
            }

            $newcontent = str_replace(['{namespace}', '{controllername}', '{controller-identifier}', '{list-template}', '{create-template}', '{edit-template}', '{delete-template}', '{requestValidationName}'], [$namespace, $controller_name, $controller_identifier, $list_template, $create_template, $edit_template, $delete_template, $request_validation_name], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $controller_name . ' generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $controller_name . ' could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
