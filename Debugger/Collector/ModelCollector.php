<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * ModelCollector collects loaded models for the DebugBar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Helpers\File\Paths;

class ModelCollector extends DataCollector implements Renderable
{
    protected array $models = [];

    protected array $uniqueModels = [];

    protected int $totalInstantiations = 0;

    public function addModel(string $modelClass, array $attributes = []): void
    {
        $this->totalInstantiations++;

        $modelClass = str_replace(Paths::basePath(), '', $modelClass);
        $modelClass = str_replace('\\', '/', $modelClass);

        $primaryKey = $attributes['id'] ?? null;
        $signature = $modelClass . '::' . ($primaryKey ?? 'no_id_' . uniqid());

        if (! isset($this->uniqueModels[$signature])) {
            $encoded = json_encode($attributes, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($encoded === false) {
                $encoded = 'JSON Error: ' . json_last_error_msg();
            }

            $this->models[] = [
                'model' => $modelClass,
                'attributes' => $encoded,
                'primary_key' => $primaryKey,
            ];

            $this->uniqueModels[$signature] = true;
        }
    }

    public function collect(): array
    {
        $data = [];
        $counts = [];

        foreach ($this->models as $index => $model) {
            $name = $model['model'];
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            $pkDisplay = $model['primary_key'] ? " (ID: {$model['primary_key']})" : '';
            $key = $name . $pkDisplay;
            $data[$key] = $model['attributes'];
        }

        ksort($data);

        $summary = [];
        foreach ($counts as $name => $count) {
            $summary[] = "$name: $count";
        }

        // Format statistics as an array for better display
        $statistics = [
            'Total Instantiations' => $this->totalInstantiations,
            'Unique Models' => count($this->models),
            'Model Types' => count($counts),
            '' => '─────────────────',
        ];

        foreach ($counts as $name => $count) {
            $statistics[$name] = $count;
        }

        return [
            'statistics' => $statistics,
            'models' => $data,
            'count' => count($this->models),
        ];
    }

    public function getName(): string
    {
        return 'models';
    }

    public function getWidgets(): array
    {
        return [
            'Models' => [
                'icon' => 'cube',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'models.models',
                'default' => '{}',
            ],
            'Models:badge' => [
                'map' => 'models.count',
                'default' => '0',
            ],
            'Model Statistics' => [
                'icon' => 'chart-bar',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'models.statistics',
                'default' => '{}',
            ],
        ];
    }
}
