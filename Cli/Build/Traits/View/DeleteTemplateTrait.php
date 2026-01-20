<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting View Template components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\View;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteTemplateTrait
{
    public function template(string $template, ?string $module = null): array
    {
        if ($module) {
            $module_name = ucfirst($module);
            $directory = Paths::appSourcePath($module_name . '/Views/Templates');
        } else {
            $directory = Paths::appPath('Views/Templates');
        }

        $template_name = strtolower($template);
        $file = $directory . '/' . $template_name . '.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $template_name . ' template does not exist.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $template_name . ' template deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $template_name . ' template could not be deleted.',
        ];
    }
}
