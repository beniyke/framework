<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Task/Job components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Task;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateTaskTrait
{
    public function task(string $task_name, ?string $module = null): array
    {
        $default_template_path = Paths::cliPath('Build/Templates/');
        $custom_template_path = Paths::storagePath('build/templates/');
        $template = 'TaskTemplate.php.stub';
        $param = "'" . strtolower($task_name) . "'";

        $task_name = ucfirst($task_name) . 'Task';

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

            $task_name_dir = $directory . '/Tasks/';
        } else {
            $directory = Paths::appPath('Tasks');
            $namespace = 'App';
            $task_name_dir = $directory . '/';
        }

        $custom_template = $custom_template_path . $template;
        $default_template = $default_template_path . $template;

        FileSystem::mkdir($task_name_dir);
        $file = $task_name_dir . $task_name . '.php';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $task_name . ' task build not successful, ' . $task_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{taskname}') === false) {
                return [
                    'status' => false,
                    'message' => 'Task template file not found.',
                ];
            }

            $newcontent = str_replace(['{namespace}', '{taskname}'], [$namespace, $task_name], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $task_name . ' generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $task_name . ' could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
