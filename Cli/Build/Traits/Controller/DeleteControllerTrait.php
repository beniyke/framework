<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Controller components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Controller;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteControllerTrait
{
    public function controller(string $controller, string $module): array
    {
        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $controller_name = ucfirst($controller) . 'Controller';
        $file = $directory . '/Controllers/' . $controller_name . '.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $controller_name . ' file not found.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $controller_name . ' deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $controller_name . ' could not be deleted.',
        ];
    }
}
