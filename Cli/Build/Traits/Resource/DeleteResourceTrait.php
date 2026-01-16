<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Resource components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Resource;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteResourceTrait
{
    public function resource(string $resource, string $module): array
    {
        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);

        $resource_name = ucfirst($resource);
        $file = $directory . '/Resources/' . $resource_name . 'Resource.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $resource_name . ' resource does not exist.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $resource_name . ' resource deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $resource_name . ' resource could not be deleted.',
        ];
    }
}
