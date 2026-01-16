<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * TimelineCollector collects timeline events for the DebugBar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Collector;

use DebugBar\DataCollector\TimeDataCollector;

class TimelineCollector extends TimeDataCollector
{
    public function getName(): string
    {
        return 'timeline';
    }

    public function getWidgets(): array
    {
        return [
            'Time' => [
                'icon' => 'clock-o',
                'tooltip' => 'Request Duration',
                'map' => 'timeline.duration_str',
                'default' => "'0ms'",
            ],
            'Timeline' => [
                'icon' => 'tasks',
                'widget' => 'PhpDebugBar.Widgets.TimelineWidget',
                'map' => 'timeline',
                'default' => '{}',
            ],
        ];
    }
}
