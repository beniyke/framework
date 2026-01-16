<?php

declare(strict_types=1);

namespace Cli\Build\Traits\Event;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateEventTrait
{
    public function event(string $name, ?string $module = null): array
    {
        $templatePath = Paths::cliPath('Build/Templates/EventTemplate.php.stub');

        if (! FileSystem::exists($templatePath)) {
            return [
                'status' => false,
                'message' => 'Event template not found.',
            ];
        }

        // Determine directory and namespace
        if ($module) {
            $directory = Paths::appSourcePath(ucfirst($module) . '/Events');
            $namespace = 'App\\' . ucfirst($module) . '\\Events';
        } else {
            $directory = Paths::appPath('Events');
            $namespace = 'App\\Events';
        }

        $className = ucfirst($name);

        $filePath = $directory . '/' . $className . '.php';

        if (FileSystem::exists($filePath)) {
            return [
                'status' => false,
                'message' => $className . '.php already exists.',
            ];
        }

        // Ensure directory exists
        FileSystem::mkdir($directory, 0755, true);

        // Generate content from template
        $templateContent = FileSystem::get($templatePath);
        $newContent = str_replace(
            ['{filenamespace}', '{classname}'],
            [$namespace, $className],
            $templateContent
        );

        $created = FileSystem::put($filePath, $newContent);

        if ($created) {
            return [
                'status' => true,
                'message' => $className . '.php event successfully created.',
                'path' => $filePath,
            ];
        }

        return [
            'status' => false,
            'message' => $className . '.php event could not be created.',
        ];
    }
}
