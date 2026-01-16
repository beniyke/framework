<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Middleware components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Middleware;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateMiddlewareTrait
{
    public function middleware(string $middleware, string $group = 'Web'): array
    {
        $default_template = Paths::cliPath('Build/Templates/MiddlewareTemplate.php.stub');
        $custom_template = Paths::storagePath('build/MiddlewareTemplate.php.stub');

        // Group: Web or Api
        $group_name = ucfirst($group);

        // Path: App/Middleware/Web or App/Middleware/Api
        $directory = Paths::basePath('App/Middleware/' . $group_name);
        $namespace = 'App\\Middleware\\' . $group_name;

        if (! FileSystem::exists($directory)) {
            FileSystem::mkdir($directory);
        }

        $middleware_name = ucfirst($middleware);
        if (! str_ends_with($middleware_name, 'Middleware')) {
            $middleware_name .= 'Middleware';
        }

        $file = $directory . '/' . $middleware_name . '.php';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $middleware_name . ' middleware build not successful, ' . $middleware_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{middlewarename}') === false) {
                return [
                    'status' => false,
                    'message' => 'middleware template file not found.',
                ];
            }

            $newcontent = str_replace(
                ['{namespace}', '{middlewarename}'],
                [$namespace, $middleware_name],
                FileSystem::get($templatefile)
            );

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $middleware_name . ' middleware generated successfully in ' . $group_name . ' group.',
                ];
            }

            return [
                'status' => false,
                'message' => $middleware_name . ' middleware could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
