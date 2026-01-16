<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Request Validation components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Validation;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateValidationTrait
{
    public function requestValidation(string $request_validation, string $module, string $validation_type): array
    {
        $default_template_path = Paths::cliPath('Build/Templates/');
        $custom_template_path = Paths::storagePath('build/');
        $validation_type = ucfirst($validation_type);
        $template = 'RequestValidationTemplate.php.stub';
        $param = "'" . strtolower($request_validation) . "'";

        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);
        $namespace = 'App\\' . $module_name;

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $custom_template = $custom_template_path . $template;
        $default_template = $default_template_path . $template;
        $request_validation_name = ucfirst($request_validation) . $validation_type . 'RequestValidation';
        $request_validation_dir = $directory . '/Validations/' . $validation_type . '/';

        FileSystem::mkdir($request_validation_dir);

        $file = $request_validation_dir . $request_validation_name . '.php';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $request_validation_name . ' form validation build not successful, ' . $request_validation_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{validationName}') === false) {
                return [
                    'status' => false,
                    'message' => 'Form validation template file not found.',
                ];
            }

            $newcontent = str_replace(['{namespace}', '{validationName}', '{requestName}', '{validationType}'], [$namespace, $request_validation_name, ucfirst($request_validation), $validation_type], FileSystem::get($templatefile));
            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $request_validation_name . ' generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $request_validation_name . ' could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
