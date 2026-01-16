<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Resource components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Resource;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateResourceTrait
{
    public function resource(string $resource, string $module): array
    {
        $default_template = Paths::cliPath('Build/Templates/ResourceTemplate.php.stub');
        $custom_template = Paths::storagePath('build/ResourceTemplate.php.stub');

        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);
        $namespace = 'App\\' . $module_name;

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $resource_name = ucfirst($resource);
        $file = $directory . '/Resources/' . $resource_name . 'Resource.php';

        FileSystem::mkdir($directory . '/Resources');

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $resource_name . ' resource build not successful, ' . $resource_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{resourcename}') === false) {
                return [
                    'status' => false,
                    'message' => 'resource template file not found.',
                ];
            }

            $newcontent = str_replace(['{namespace}', '{resourcename}'], [$namespace, $resource_name . 'Resource'], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $resource_name . ' resource generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $resource_name . ' resource could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
