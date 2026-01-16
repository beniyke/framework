<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Request components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Request;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteRequestTrait
{
    public function request(string $request, string $module): array
    {
        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);

        $request_name = ucfirst($request);
        $file = $directory . '/Requests/' . $request_name . 'Request.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $request_name . ' request does not exist.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $request_name . ' request deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $request_name . ' request could not be deleted.',
        ];
    }
}
