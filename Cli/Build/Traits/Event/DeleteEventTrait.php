<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait DeleteEventTrait implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Event;

trait DeleteEventTrait
{
    public function event(string $name, ?string $module = null): array
    {
        $path = 'System/Events';
        if ($module) {
            $path = 'App/' . ucfirst($module) . '/Events';
        } else {
            $path = 'App/Events';
        }

        $this->path($path);

        return $this->file($name);
    }
}
