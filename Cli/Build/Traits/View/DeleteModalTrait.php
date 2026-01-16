<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting View Modal components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\View;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteModalTrait
{
    public function modal(string $modal, ?string $module = null): array
    {
        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name) . '/Views/Templates';

        $modal_name = strtolower($modal);
        $file = $directory . '/modals/' . $modal_name . '.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $modal_name . ' modal does not exist.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $modal_name . ' modal deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $modal_name . ' modal could not be deleted.',
        ];
    }
}
