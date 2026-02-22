<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Builds model file content from parsed migration data.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build;

class SmartModelBuilder
{
    private array $data;

    private array $columns;

    private array $foreignKeys;

    private bool $hasTimestamps;

    private bool $hasSoftDeletes;

    public function __construct(array $parsedMigration)
    {
        $this->data = $parsedMigration;
        $this->columns = $parsedMigration['columns'];
        $this->foreignKeys = $parsedMigration['foreignKeys'];
        $this->hasTimestamps = $parsedMigration['hasTimestamps'];
        $this->hasSoftDeletes = $parsedMigration['hasSoftDeletes'];
    }

    public function buildReplacements(string $namespace, string $modelName, string $tableName): array
    {
        $fillable = MigrationParser::inferFillable($this->columns);
        $casts = MigrationParser::inferCasts($this->columns, $this->hasTimestamps);
        $hidden = MigrationParser::inferHidden($this->columns);
        $relationships = MigrationParser::inferRelationships($this->columns, $this->foreignKeys);
        $scopes = MigrationParser::inferScopes($this->columns);
        $properties = MigrationParser::inferProperties($this->columns, $this->hasTimestamps, $this->hasSoftDeletes);

        return [
            '{namespace}' => $namespace,
            '{modelname}' => $modelName,
            '{inferredTableName}' => $tableName,
            '{fillable}' => $this->formatFillable($fillable),
            '{casts}' => $this->formatCasts($casts),
            '{hidden}' => $this->formatHidden($hidden),
            '{properties}' => $this->formatProperties($properties),
            '{relationships}' => $this->formatRelationships($relationships),
            '{scopes}' => $this->formatScopes($scopes),
            '{traits}' => $this->formatTraits(),
            '{traitImports}' => $this->formatTraitImports(),
            '{relationImports}' => $this->formatRelationImports($relationships),
            '{softDeletesProperty}' => $this->hasSoftDeletes ? "    protected bool \$softDeletes = true;\n\n" : '',
        ];
    }

    private function formatFillable(array $fillable): string
    {
        if (empty($fillable)) {
            return '';
        }

        $lines = array_map(fn (string $field) => "        '{$field}',", $fillable);

        return implode("\n", $lines) . "\n";
    }

    private function formatCasts(array $casts): string
    {
        if (empty($casts)) {
            return '';
        }

        $lines = array_map(
            fn (string $col, string $type) => "        '{$col}' => '{$type}',",
            array_keys($casts),
            array_values($casts)
        );

        return "    protected array \$casts = [\n" . implode("\n", $lines) . "\n    ];\n\n";
    }

    private function formatHidden(array $hidden): string
    {
        if (empty($hidden)) {
            return '';
        }

        $items = array_map(fn (string $field) => "'{$field}'", $hidden);

        return "    protected array \$hidden = [" . implode(', ', $items) . "];\n\n";
    }

    private function formatProperties(array $properties): string
    {
        $lines = [];

        foreach ($properties as $prop) {
            $type = $prop['nullable'] ? "?{$prop['type']}" : $prop['type'];
            $pad = str_repeat(' ', max(1, 16 - strlen($type)));
            $lines[] = " * @property {$type}{$pad}\${$prop['name']}";
        }

        return implode("\n", $lines) . "\n";
    }

    private function formatRelationships(array $relationships): string
    {
        if (empty($relationships)) {
            return '';
        }

        $methods = [];

        foreach ($relationships as $rel) {
            $methodName = lcfirst($rel['model']);
            $modelClass = $rel['model'];

            if ($rel['confirmed']) {
                $methods[] = "    public function {$methodName}(): BelongsTo\n    {\n        return \$this->belongsTo({$modelClass}::class, '{$rel['column']}');\n    }";
            } else {
                $methods[] = "    // public function {$methodName}(): BelongsTo\n    // {\n    //     return \$this->belongsTo({$modelClass}::class, '{$rel['column']}');\n    // }";
            }
        }

        return implode("\n\n", $methods) . "\n\n";
    }

    private function formatScopes(array $scopes): string
    {
        if (empty($scopes)) {
            return '';
        }

        $methods = [];

        foreach ($scopes as $scope) {
            if ($scope['type'] === 'boolean') {
                $value = $scope['value'] ? 'true' : 'false';
                $methods[] = "    public function scope{$scope['name']}(Builder \$query): Builder\n    {\n        return \$query->where('{$scope['column']}', {$value});\n    }";
            } elseif ($scope['type'] === 'enum') {
                $methods[] = "    public function scope{$scope['name']}(Builder \$query, string \$value): Builder\n    {\n        return \$query->where('{$scope['column']}', \$value);\n    }";
            }
        }

        return implode("\n\n", $methods) . "\n";
    }

    private function formatTraits(): string
    {
        if (! $this->hasSoftDeletes) {
            return '';
        }

        return "    use SoftDeletes;\n\n";
    }

    private function formatTraitImports(): string
    {
        if (! $this->hasSoftDeletes) {
            return '';
        }

        return "use Database\\Traits\\SoftDeletes;\n";
    }

    private function formatRelationImports(array $relationships): string
    {
        if (empty($relationships)) {
            return '';
        }

        return "use Database\\Relations\\BelongsTo;\n";
    }
}
