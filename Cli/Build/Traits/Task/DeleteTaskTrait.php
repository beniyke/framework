<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Task/Job components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Task;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteTaskTrait
{
    public function task(string $task_name, ?string $module = null): array
    {
        if ($module) {
            $module_name = ucfirst($module);
            $directory = Paths::appSourcePath($module_name);

            if (! FileSystem::exists($directory)) {
                return [
                    'status' => false,
                    'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
                ];
            }

            $task_name = ucfirst($task_name) . 'Task';
            $file = $directory . '/Tasks/' . $task_name . '.php';
        } else {
            $directory = Paths::appPath('Tasks');
            $task_name = ucfirst($task_name) . 'Task';
            $file = $directory . '/' . $task_name . '.php';
        }

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $task_name . ' file not found.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $task_name . ' deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $task_name . ' could not be deleted.',
        ];
    }
}
