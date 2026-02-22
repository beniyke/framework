<?php

declare(strict_types=1);

namespace Cli\Build\Traits\Schedule;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateScheduleTrait
{
    /**
 * Anchor Framework
 *
 * Generate a new schedule class.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */
    public function schedule(string $name, ?string $module = null): array
    {
        $classname = ucfirst($name) . 'Schedule';

        if ($module) {
            $directory = Paths::appSourcePath(ucfirst($module) . '/Schedules');
            $namespace = ucfirst($module) . '\\Schedules';
        } else {
            $directory = Paths::appPath('Schedules');
            $namespace = 'App\\Schedules';
        }

        $templatefile = Paths::cliPath('Build/Templates/ScheduleTemplate.php.stub');

        if (! FileSystem::exists($templatefile)) {
            return [
                'status' => false,
                'message' => 'Schedule template file not found.',
            ];
        }

        FileSystem::mkdir($directory, 0755, true);

        $file = $directory . '/' . $classname . '.php';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $classname . ' schedule already exists.',
            ];
        }

        $template = FileSystem::get($templatefile);
        $content = str_replace(
            ['{namespace}', '{classname}'],
            [$namespace, $classname],
            $template
        );

        $generated = FileSystem::put($file, $content);

        if ($generated) {
            return [
                'status' => true,
                'message' => $classname . ' schedule generated successfully.',
                'path' => $file,
            ];
        }

        return [
            'status' => false,
            'message' => $classname . ' schedule could not be generated.',
        ];
    }

    public function deleteSchedule(string $name, ?string $module = null): array
    {
        $classname = ucfirst($name) . 'Schedule';

        if ($module) {
            $directory = Paths::appSourcePath(ucfirst($module) . '/Schedules');
        } else {
            $directory = Paths::appPath('Schedules');
        }

        $file = $directory . '/' . $classname . '.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $classname . ' schedule not found.',
            ];
        }

        if (FileSystem::delete($file)) {
            return [
                'status' => true,
                'message' => $classname . ' schedule deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $classname . ' schedule could not be deleted.',
        ];
    }
}
