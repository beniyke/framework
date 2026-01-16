<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * ViewCollector collects view rendering data for the DebugBar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Helpers\File\Paths;

class ViewCollector extends DataCollector implements Renderable
{
    protected array $views = [];

    public function addView(string $viewName, array $data = []): void
    {
        $viewName = str_replace(Paths::basePath(), '', $viewName);
        $viewName = str_replace('\\', '/', $viewName);

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            $encoded = 'JSON Error: ' . json_last_error_msg();
        }

        $this->views[] = [
            'name' => $viewName,
            'data' => $encoded,
        ];
    }

    public function collect(): array
    {
        $data = [];
        foreach ($this->views as $index => $view) {
            $key = $view['name'] . ' #' . ($index + 1);
            $data[$key] = $view['data'];
        }

        return [
            'views' => $data,
            'count' => count($this->views),
        ];
    }

    public function getName(): string
    {
        return 'views';
    }

    public function getWidgets(): array
    {
        return [
            'Views' => [
                'icon' => 'eye',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'views.views',
                'default' => '{}',
            ],
            'Views:badge' => [
                'map' => 'views.count',
                'default' => '0',
            ],
        ];
    }
}
