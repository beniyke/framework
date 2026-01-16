<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Request components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Request;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateRequestTrait
{
    public function request(string $request, string $module): array
    {
        $default_template = Paths::cliPath('Build/Templates/RequestTemplate.php.stub');
        $custom_template = Paths::storagePath('build/RequestTemplate.php.stub');

        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);
        $namespace = 'App\\' . $module_name;

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $request_name = ucfirst($request);
        $file = $directory . '/Requests/' . $request_name . 'Request.php';

        FileSystem::mkdir($directory . '/Requests');

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $request_name . ' request build not successful, ' . $request_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{requestname}') === false) {
                return [
                    'status' => false,
                    'message' => 'request template file not found.',
                ];
            }

            $newcontent = str_replace(['{namespace}', '{requestname}'], [$namespace, $request_name . 'Request'], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $request_name . ' request generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $request_name . ' request could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
