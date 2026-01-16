<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * View helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Helpers\Html\Assets;
use Helpers\Html\HtmlBuilder;
use Helpers\Html\HtmlComponent;

if (! function_exists('assets')) {
    function assets(string $file): string
    {
        return resolve(Assets::class)->url($file);
    }
}

if (! function_exists('component')) {
    function component(string $name): HtmlComponent
    {
        return container()->make(HtmlComponent::class, compact('name'));
    }
}

if (! function_exists('html')) {
    function html(): HtmlBuilder
    {
        return new HtmlBuilder();
    }
}
