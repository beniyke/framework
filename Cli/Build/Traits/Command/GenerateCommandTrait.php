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
    public function command(string $command): array
    {
        $directory = Paths::appPath('Commands');
        $templatefile = Paths::cliPath('Build/Templates/CommandTemplate.php.stub');

        if (! FileSystem::exists($templatefile) || strpos(FileSystem::get($templatefile), '{commandname}') === false) {
            return [
                'status' => false,
                'message' => 'Command template file not found.',
            ];
        }

        $command_name = ucfirst($command);

        FileSystem::mkdir($directory);

        $file = $directory . '/' . $command_name . 'Command.php';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $command_name . ' command already exist.',
            ];
        }

        $newcontent = str_replace(['{commandname}'], [$command_name . 'Command'], FileSystem::get($templatefile));

        $generated = FileSystem::put($file, $newcontent);

        if ($generated) {
            return [
                'status' => true,
                'message' => $command_name . ' command generated successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $command_name . ' command could not be generated.',
        ];
    }
}
