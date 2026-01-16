<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating View Modal components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\View;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateModalTrait
{
    public function modal(string $modal, string $module, ?string $endpoint = null): array
    {
        $default_template = Paths::cliPath('Build/Templates/ViewModalTemplate.php.stub');
        $custom_template = Paths::storagePath('build/ViewModalTemplate.php.stub');

        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name) . '/Views/Templates';

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $modal_name = strtolower($modal);
        $file = $directory . '/modals/' . $modal_name . '.php';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $modal_name . ' modal build not successful, ' . $modal_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;
        $endpoint = empty($endpoint) ? '{endpoint}' : $endpoint;

        if (FileSystem::exists($templatefile)) {
            $newcontent = str_replace(['{endpoint}'], [$endpoint], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $modal_name . ' modal generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $modal_name . ' modal could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
