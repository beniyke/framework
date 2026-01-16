<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Service components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Service;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateServiceTrait
{
    public function service(string $service, string $module): array
    {
        $default_template = Paths::cliPath('Build/Templates/ServiceTemplate.php.stub');
        $custom_template = Paths::storagePath('build/ServiceTemplate.php.stub');

        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);
        $namespace = 'App\\' . $module_name;

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $service_name = ucfirst($service);
        $file = $directory . '/Services/' . $service_name . 'Service.php';

        FileSystem::mkdir($directory . '/Services');

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $service_name . ' service build not successful, ' . $service_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{servicename}') === false) {
                return [
                    'status' => false,
                    'message' => 'service template file not found.',
                ];
            }

            $newcontent = str_replace(['{namespace}', '{servicename}'], [$namespace, $service_name . 'Service'], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $service_name . ' service generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $service_name . ' service could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
