<?php

declare(strict_types=1);

namespace Cli\Build\Traits\Listener;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateListenerTrait
{
    public function listener(string $name, ?string $module = null): array
    {
        $templatePath = Paths::cliPath('Build/Templates/ListenerTemplate.php.stub');

        if (! FileSystem::exists($templatePath)) {
            return [
                'status' => false,
                'message' => 'Listener template not found.',
            ];
        }

        // Determine directory and namespace
        if ($module) {
            $directory = Paths::appSourcePath(ucfirst($module) . '/Listeners');
            $namespace = 'App\\' . ucfirst($module) . '\\Listeners';
        } else {
            $directory = Paths::appPath('Listeners');
            $namespace = 'App\\Listeners';
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
                'message' => $className . '.php listener successfully created.',
                'path' => $filePath,
            ];
        }

        return [
            'status' => false,
            'message' => $className . '.php listener could not be created.',
        ];
    }
}
