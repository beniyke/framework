<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Model components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Model;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteModelTrait
{
    public function model(string $model, ?string $module = null): array
    {
        if ($module) {
            $module_name = ucfirst($module);
            $directory = Paths::appSourcePath($module_name);
        } else {
            $directory = Paths::appPath();
        }

        $model_name = ucfirst($model);
        $file = $directory . '/Models/' . $model_name . '.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $model_name . ' model does not exist.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $model_name . ' model deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $model_name . ' model could not be deleted.',
        ];
    }
}
