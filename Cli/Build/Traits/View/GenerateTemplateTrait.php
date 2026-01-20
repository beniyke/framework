<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating View Template components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\View;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateTemplateTrait
{
    public function template(string $template, ?string $module = null, ?string $type = null): array
    {
        $default_template = Paths::cliPath('Build/Templates/ViewTemplate.php.stub');
        $custom_template = Paths::storagePath('build/ViewTemplate.php.stub');

        if ($module) {
            $module_name = ucfirst($module);
            $directory = Paths::appSourcePath($module_name) . '/Views/Templates';

            if (! FileSystem::exists(Paths::appSourcePath($module_name))) {
                return [
                    'status' => false,
                    'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
                ];
            }
        } else {
            $directory = Paths::appPath() . '/Views/Templates';
        }

        $template_name = strtolower($template);

        $file = $directory . '/' . $template_name . '.php';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $template_name . ' template build not successful, ' . $template_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{template-name}') === false) {
                return [
                    'status' => false,
                    'message' => 'View template file not found',
                ];
            }

            $template_name = ucfirst($template_name);
            $template_type = empty($type) ? '{template-type}' : $type . '-template';
            $newcontent = str_replace(['{template-name}', '{template}'], [str_replace(['-', '_'], ' ', ucwords($template_name)), $template_type], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $template_name . ' template generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $template_name . ' template could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
