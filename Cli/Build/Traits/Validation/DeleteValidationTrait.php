<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Request Validation components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Validation;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteValidationTrait
{
    public function requestValidation(string $request_validation, string $validation_type, ?string $module = null): array
    {
        if ($module) {
            $module_name = ucfirst($module);
            $directory = Paths::appSourcePath($module_name);

            if (! FileSystem::exists($directory)) {
                return [
                    'status' => false,
                    'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
                ];
            }
        } else {
            $directory = Paths::appPath();
        }

        $validation_type = ucfirst($validation_type);

        $request_validation_name = ucfirst($request_validation) . $validation_type . 'RequestValidation';
        $file = $directory . '/Validations/' . $validation_type . '/' . $request_validation_name . '.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $request_validation_name . ' file not found.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $request_validation_name . ' deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $request_validation_name . ' could not be deleted.',
        ];
    }
}
