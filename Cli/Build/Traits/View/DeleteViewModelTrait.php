<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting View Model components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\View;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteViewModelTrait
{
    public function view_model(string $view_model, ?string $module = null): array
    {
        if ($module) {
            $module_name = ucfirst($module);
            $directory = Paths::appSourcePath($module_name);
        } else {
            $directory = Paths::appPath();
        }

        $view_model_name = ucfirst($view_model);
        $file = $directory . '/Views/Models/' . $view_model_name . 'ViewModel.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $view_model_name . ' view model does not exist.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $view_model_name . ' view model deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $view_model_name . ' view model could not be deleted.',
        ];
    }
}
