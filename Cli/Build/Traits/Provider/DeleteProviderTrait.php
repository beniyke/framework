<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Provider components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Provider;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteProviderTrait
{
    public function provider(string $provider): array
    {
        $directory = Paths::appPath('Providers');
        $provider_name = ucfirst($provider);
        $file = $directory . '/' . $provider_name . 'Provider.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $provider_name . ' provider file does not exist.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $provider_name . ' provider deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $provider_name . ' provider could not be deleted.',
        ];
    }
}
