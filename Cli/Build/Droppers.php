<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Droppers Class
 * Handles the deletion of various system components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build;

use Cli\Build\Traits\Action\DeleteActionTrait;
use Cli\Build\Traits\Command\DeleteCommandTrait;
use Cli\Build\Traits\Controller\DeleteControllerTrait;
use Cli\Build\Traits\Model\DeleteModelTrait;
use Cli\Build\Traits\Notification\DeleteNotificationTrait;
use Cli\Build\Traits\Provider\DeleteProviderTrait;
use Cli\Build\Traits\Request\DeleteRequestTrait;
use Cli\Build\Traits\Resource\DeleteResourceTrait;
use Cli\Build\Traits\Service\DeleteServiceTrait;
use Cli\Build\Traits\Task\DeleteTaskTrait;
use Cli\Build\Traits\Validation\DeleteValidationTrait;
use Cli\Build\Traits\View\DeleteModalTrait;
use Cli\Build\Traits\View\DeleteTemplateTrait;
use Cli\Build\Traits\View\DeleteViewModelTrait;
use Exception;
use Helpers\File\FileSystem;
use Helpers\File\Paths;

class Droppers
{
    use DeleteActionTrait;
    use DeleteCommandTrait;
    use DeleteControllerTrait;
    use DeleteModalTrait;
    use DeleteModelTrait;
    use DeleteNotificationTrait;
    use DeleteProviderTrait;
    use DeleteRequestTrait;
    use DeleteResourceTrait;
    use DeleteServiceTrait;
    use DeleteTaskTrait;
    use DeleteTemplateTrait;
    use DeleteValidationTrait;
    use DeleteViewModelTrait;
    use Traits\Middleware\DeleteMiddlewareTrait;
    use Traits\Event\DeleteEventTrait;
    use Traits\Listener\DeleteListenerTrait;

    public static $instance;

    private $path;

    private $directory;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new Exception('Cannot unserialize a singleton.');
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function directory(string $name, ?string $sub_directories = null): array
    {
        $directory_name = ucfirst($name);
        $full_path = $this->path . '/' . $directory_name;
        $directory_path = Paths::basePath($full_path);

        if (! FileSystem::isDir($directory_path)) {
            return [
                'status' => false,
                'message' => 'The directory ' . $directory_name . ' does not exist',
            ];
        }

        if (! empty($sub_directories)) {
            $split = explode(',', $sub_directories);

            foreach ($split as $directory) {
                $dir = $directory_path . '/' . $directory;

                if (FileSystem::isDir($dir)) {
                    FileSystem::delete($dir);
                }
            }

            return [
                'status' => true,
                'message' => $sub_directories . ' sub-directories successfully deleted.',
            ];
        }

        $deleted = FileSystem::delete($directory_path);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $directory_name . ' directory successfully deleted.',
            ];
        }

        return [
            'status' => false,
            'message' => $directory_name . ' directory could not be deleted.',
        ];
    }

    public function file(string $name): array
    {
        $name_parts = explode('/', str_replace('\\', '/', $name));
        $file_name = ucfirst(array_pop($name_parts)) . '.php';

        $relative_path_segments = array_map('ucfirst', $name_parts);
        $relative_path = empty($relative_path_segments) ? '' : '/' . implode('/', $relative_path_segments);

        $full_path = $this->path . $relative_path . '/' . $file_name;
        $file_path = Paths::basePath($full_path);

        if (! FileSystem::exists($file_path)) {
            return [
                'status' => false,
                'message' => 'The file ' . $file_name . ' does not exist',
            ];
        }

        $deleted = FileSystem::delete($file_path);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $file_name . ' file successfully deleted.',
            ];
        }

        return [
            'status' => false,
            'message' => $file_name . ' file could not be deleted.',
        ];
    }
}
