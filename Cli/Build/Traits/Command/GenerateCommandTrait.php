<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Command components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Command;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateCommandTrait
{
    public function command(string $command, ?string $module = null): array
    {
        $directory = Paths::appPath('Commands');
        $namespace = 'App\\Commands';

        if ($module) {
            $directory = Paths::appSourcePath(ucfirst($module) . '/Commands');
            $namespace = ucfirst($module) . '\\Commands';
        }

        $templatefile = Paths::cliPath('Build/Templates/CommandTemplate.php.stub');

        if (! FileSystem::exists($templatefile)) {
            return [
                'status' => false,
                'message' => 'Command template file not found.',
            ];
        }

        $command_name = ucfirst($command);

        FileSystem::mkdir($directory, 0755, true);

        $file = $directory . '/' . $command_name . 'Command.php';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $command_name . ' command already exists.',
            ];
        }

        $template = FileSystem::get($templatefile);
        $newcontent = str_replace(
            ['{namespace}', '{commandname}'],
            [$namespace, $command_name . 'Command'],
            $template
        );

        $generated = FileSystem::put($file, $newcontent);

        if ($generated) {
            return [
                'status' => true,
                'message' => $command_name . ' command generated successfully.',
                'path' => $file,
            ];
        }

        return [
            'status' => false,
            'message' => $command_name . ' command could not be generated.',
        ];
    }
}
