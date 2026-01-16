<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Action components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Action;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateActionTrait
{
    public function action(string $action, string $module): array
    {
        $default_template = Paths::cliPath('Build/Templates/ActionTemplate.php.stub');
        $custom_template = Paths::storagePath('build/ActionTemplate.php.stub');

        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);
        $namespace = 'App\\' . $module_name;

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $action_name = ucfirst($action);
        $file = $directory . '/Actions/' . $action_name . 'Action.php';

        FileSystem::mkdir($directory . '/Actions');

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $action_name . ' action build not successful, ' . $action_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{actionname}') === false) {
                return [
                    'status' => false,
                    'message' => 'action template file not found.',
                ];
            }

            $newcontent = str_replace(['{namespace}', '{actionname}'], [$namespace, $action_name . 'Action'], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $action_name . ' action generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $action_name . ' action could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
