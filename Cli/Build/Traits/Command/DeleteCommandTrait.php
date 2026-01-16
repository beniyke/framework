<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Command components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Command;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteCommandTrait
{
    public function command(string $command): array
    {
        $directory = Paths::appPath('Commands');
        $command_name = ucfirst($command);
        $file = $directory . '/' . $command_name . 'Command.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $command_name . ' command file does not exist.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $command_name . ' command deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $command_name . ' command could not be deleted.',
        ];
    }
}
