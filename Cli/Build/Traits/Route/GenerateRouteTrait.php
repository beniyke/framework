<?php

declare(strict_types=1);

namespace Cli\Build\Traits\Route;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

/**
 * Trait for generating and deleting manual route map files.
 */
trait GenerateRouteTrait
{
    public function routeMap(string $module): array
    {
        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name . DIRECTORY_SEPARATOR . 'Route');
        $file = $directory . DIRECTORY_SEPARATOR . 'map.php';

        if (!FileSystem::isDir(Paths::appSourcePath($module_name))) {
            return [
                'status' => false,
                'message' => "The module '$module_name' does not exist.",
            ];
        }

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => "Route map already exists for module '$module_name'.",
            ];
        }

        FileSystem::mkdir($directory, 0755, true);

        $template_file = Paths::systemPath('Cli/Build/Templates/RouteMapTemplate.php.stub');
        $content = FileSystem::get($template_file);
        $content = str_replace('{module}', $module_name, $content);

        if (FileSystem::put($file, $content)) {
            return [
                'status' => true,
                'message' => "Route map successfully created for module '$module_name'.",
                'path' => $file,
            ];
        }

        return [
            'status' => false,
            'message' => "Failed to create route map for module '$module_name'.",
        ];
    }

    public function deleteRouteMap(string $module): array
    {
        $module_name = ucfirst($module);
        $file = Paths::appSourcePath($module_name . DIRECTORY_SEPARATOR . 'Route' . DIRECTORY_SEPARATOR . 'map.php');

        if (!FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => "Route map does not exist for module '$module_name'.",
            ];
        }

        if (FileSystem::delete($file)) {
            return [
                'status' => true,
                'message' => "Route map successfully deleted for module '$module_name'.",
            ];
        }

        return [
            'status' => false,
            'message' => "Failed to delete route map for module '$module_name'.",
        ];
    }
}
