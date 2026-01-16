<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Middleware components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Middleware;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteMiddlewareTrait
{
    public function middleware(string $middleware, string $group = 'Web'): array
    {
        $group_name = ucfirst($group);
        $directory = Paths::basePath('App/Middleware/' . $group_name);

        $middleware_name = ucfirst($middleware);
        if (! str_ends_with($middleware_name, 'Middleware')) {
            $middleware_name .= 'Middleware';
        }

        $file = $directory . '/' . $middleware_name . '.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $middleware_name . ' middleware does not exist in ' . $group_name . ' group.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $middleware_name . ' middleware deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $middleware_name . ' middleware could not be deleted.',
        ];
    }
}
