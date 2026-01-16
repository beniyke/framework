<?php

declare(strict_types=1);

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
