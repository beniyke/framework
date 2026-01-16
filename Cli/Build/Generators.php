<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Generators Class
 * Handles the generation of various system components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build;

use Cli\Build\Traits\Action\GenerateActionTrait;
use Cli\Build\Traits\Command\GenerateCommandTrait;
use Cli\Build\Traits\Controller\GenerateControllerTrait;
use Cli\Build\Traits\Model\GenerateModelTrait;
use Cli\Build\Traits\Notification\GenerateNotificationTrait;
use Cli\Build\Traits\Provider\GenerateProviderTrait;
use Cli\Build\Traits\Request\GenerateRequestTrait;
use Cli\Build\Traits\Resource\GenerateResourceTrait;
use Cli\Build\Traits\Service\GenerateServiceTrait;
use Cli\Build\Traits\Task\GenerateTaskTrait;
use Cli\Build\Traits\Validation\GenerateValidationTrait;
use Cli\Build\Traits\View\GenerateModalTrait;
use Cli\Build\Traits\View\GenerateTemplateTrait;
use Cli\Build\Traits\View\GenerateViewModelTrait;
use Exception;
use Helpers\File\FileSystem;
use Helpers\File\Paths;

class Generators
{
    use GenerateActionTrait;
    use GenerateCommandTrait;
    use GenerateControllerTrait;
    use GenerateModalTrait;
    use GenerateModelTrait;
    use GenerateNotificationTrait;
    use GenerateProviderTrait;
    use GenerateRequestTrait;
    use GenerateResourceTrait;
    use GenerateServiceTrait;
    use GenerateTaskTrait;
    use GenerateTemplateTrait;
    use GenerateValidationTrait;
    use GenerateViewModelTrait;
    use Traits\Middleware\GenerateMiddlewareTrait;
    use Traits\Event\GenerateEventTrait;
    use Traits\Listener\GenerateListenerTrait;

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
        $this->path = trim($path, '/\\');

        return $this;
    }

    public function directory(string $name, ?string $sub_directories = null): array
    {
        $directory_name = ucfirst($name);
        $full_path = $this->path . '/' . $directory_name;
        $directory_path = Paths::basePath($full_path);

        if (FileSystem::isDir($directory_path)) {
            return [
                'status' => false,
                'message' => 'The directory ' . $directory_name . ' already exist',
            ];
        }

        $created = FileSystem::mkdir($directory_path, 0755, true);

        if ($created) {
            if (! empty($sub_directories)) {
                $split = explode(',', $sub_directories);

                foreach ($split as $directory) {
                    $dir = rtrim($directory_path, '/\\') . '/' . trim($directory);
                    FileSystem::mkdir($dir, 0755, true);
                }
            }

            return [
                'status' => true,
                'message' => $directory_name . ' directory successfully created.',
            ];
        }

        return [
            'status' => false,
            'message' => $directory_name . ' directory could not be created.',
        ];
    }

    public function file(string $name): array
    {
        $name_parts = explode('/', str_replace('\\', '/', $name));
        $class_name = ucfirst(array_pop($name_parts));

        $relative_path_segments = array_map('ucfirst', $name_parts);
        $relative_path = empty($relative_path_segments) ? '' : '/' . implode('/', $relative_path_segments);

        $full_path = $this->path . $relative_path . '/' . $class_name . '.php';
        $file_path = Paths::basePath($full_path);

        $namespace_path_parts = array_map('ucfirst', $name_parts);
        $namespace = $this->path . (empty($namespace_path_parts) ? '' : '\\' . implode('\\', $namespace_path_parts));

        if (FileSystem::exists($file_path)) {
            return [
                'status' => false,
                'message' => 'The file ' . $class_name . '.php already exists.',
            ];
        }

        $directory_only = dirname($file_path);
        FileSystem::mkdir($directory_only, 0755, true);

        $templatefile = Paths::cliPath('Build/Templates/FileTemplate.php');

        if (! FileSystem::exists($templatefile)) {
            return [
                'status' => false,
                'message' => 'File template not found.',
            ];
        }

        $template_content = FileSystem::get($templatefile);

        $newcontent = str_replace(
            ['{filenamespace}', '{classname}'],
            [$namespace, $class_name],
            $template_content
        );

        $created = FileSystem::put($file_path, $newcontent);

        if ($created) {
            return [
                'status' => true,
                'message' => $class_name . '.php file successfully created.',
            ];
        }

        return [
            'status' => false,
            'message' => $class_name . '.php file could not be created.',
        ];
    }
}
