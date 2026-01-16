<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Action components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Action;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteActionTrait
{
    public function action(string $action, string $module): array
    {
        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);

        $action_name = ucfirst($action);
        $file = $directory . '/Actions/' . $action_name . 'Action.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $action_name . ' action does not exist.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $action_name . ' action deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $action_name . ' action could not be deleted.',
        ];
    }
}
