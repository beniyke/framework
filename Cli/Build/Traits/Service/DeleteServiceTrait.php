<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Service components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Service;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteServiceTrait
{
    public function service(string $service, string $module): array
    {
        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);

        $service_name = ucfirst($service);
        $file = $directory . '/Services/' . $service_name . 'Service.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $service_name . ' service does not exist.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $service_name . ' service deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $service_name . ' service could not be deleted.',
        ];
    }
}
