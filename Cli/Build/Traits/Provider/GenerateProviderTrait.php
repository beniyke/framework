<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Provider components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Provider;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateProviderTrait
{
    public function provider(string $provider): array
    {
        $directory = Paths::appPath('Providers');
        $templatefile = Paths::cliPath('Build/Templates/ProviderTemplate.php.stub');

        if (! FileSystem::exists($templatefile) || strpos(FileSystem::get($templatefile), '{providername}') === false) {
            return [
                'status' => false,
                'message' => 'Provider template file not found.',
            ];
        }

        $provider_name = ucfirst($provider);
        FileSystem::mkdir($directory);

        $file = $directory . '/' . $provider_name . 'Provider.php';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $provider_name . ' provider already exist.',
            ];
        }

        $newcontent = str_replace(['{providername}'], [$provider_name . 'Provider'], FileSystem::get($templatefile));

        $generated = FileSystem::put($file, $newcontent);

        if ($generated) {
            return [
                'status' => true,
                'message' => $provider_name . ' provider generated successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $provider_name . ' provider could not be generated.',
        ];
    }
}
