<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait DeleteListenerTrait implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Listener;

trait DeleteListenerTrait
{
    public function listener(string $name, ?string $module = null): array
    {
        $path = 'System/Listeners';
        if ($module) {
            $path = 'App/' . ucfirst($module) . '/Listeners';
        } else {
            $path = 'App/Listeners';
        }

        $this->path($path);

        return $this->file($name);
    }
}
