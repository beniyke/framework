<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Parses migration files to extract column metadata for smart model generation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build;

use Helpers\File\FileSystem;
use RuntimeException;

class MigrationParser
{
    private const CAST_MAP = [
        'string' => 'string',
        'tinyText' => 'string',
        'text' => 'string',
        'mediumText' => 'string',
        'longText' => 'string',
        'char' => 'string',
        'uuid' => 'string',
        'ipAddress' => 'string',
        'enum' => 'string',
        'integer' => 'int',
        'tinyInteger' => 'int',
        'smallInteger' => 'int',
        'mediumInteger' => 'int',
        'bigInteger' => 'int',
        'unsignedBigInteger' => 'int',
        'unsignedInteger' => 'int',
        'unsignedSmallInteger' => 'int',
        'unsignedTinyInteger' => 'int',
        'boolean' => 'boolean',
        'decimal' => 'float',
        'float' => 'float',
        'double' => 'float',
        'dateTime' => 'datetime',
        'timestamp' => 'datetime',
        'date' => 'date',
        'time' => 'string',
        'year' => 'int',
        'json' => 'array',
        'binary' => 'string',
    ];

    private const HIDDEN_PATTERNS = [
        '/^password$/i',
        '/^secret$/i',
        '/_token$/i',
        '/_secret$/i',
    ];

    private const SYSTEM_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public static function parse(string $filePath): array
    {
        if (! FileSystem::exists($filePath)) {
            throw new RuntimeException("Migration file not found: {$filePath}");
        }

        $content = FileSystem::get($filePath);

        return [
            'tableName' => self::detectTableName($content),
            'columns' => self::parseColumns($content),
            'foreignKeys' => self::parseForeignKeys($content),
            'hasTimestamps' => self::detectTimestamps($content),
            'hasSoftDeletes' => self::detectSoftDeletes($content),
        ];
    }

    public static function detectTableName(string $content): ?string
    {
        if (preg_match('/Schema::create\s*\(\s*[\'"](\w+)[\'"]/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function parseColumns(string $content): array
    {
        $columns = [];

        preg_match_all(
            '/\$table\s*->\s*(\w+)\s*\(\s*[\'"](\w+)[\'"](?:\s*,\s*([^)]*))?\)([^;]*);/m',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $type = $match[1];
            $name = $match[2];
            $chainedCalls = $match[4] ?? '';

            if (! isset(self::CAST_MAP[$type])) {
                continue;
            }

            if (in_array($name, self::SYSTEM_COLUMNS, true)) {
                continue;
            }

            $columns[] = [
                'name' => $name,
                'type' => $type,
                'cast' => self::CAST_MAP[$type],
                'nullable' => str_contains($chainedCalls, 'nullable'),
                'default' => self::extractDefault($chainedCalls),
            ];
        }

        return $columns;
    }

    public static function parseForeignKeys(string $content): array
    {
        $foreignKeys = [];

        preg_match_all(
            '/\$table\s*->\s*foreign\s*\(\s*[\'"](\w+)[\'"]\s*\)[^;]*->\s*references\s*\(\s*[\'"](\w+)[\'"]\s*\)[^;]*->\s*on\s*\(\s*[\'"](\w+)[\'"]\s*\)/m',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $foreignKeys[] = [
                'column' => $match[1],
                'references' => $match[2],
                'on' => $match[3],
            ];
        }

        return $foreignKeys;
    }

    public static function detectTimestamps(string $content): bool
    {
        return (bool) preg_match('/\$table\s*->\s*(timestamps|dateTimestamps)\s*\(/', $content);
    }

    public static function detectSoftDeletes(string $content): bool
    {
        return (bool) preg_match('/\$table\s*->\s*(softDeletes|softDeletesTz)\s*\(/', $content);
    }

    public static function inferFillable(array $columns): array
    {
        return array_column($columns, 'name');
    }

    public static function inferCasts(array $columns, bool $hasTimestamps): array
    {
        $casts = ['id' => 'int'];

        foreach ($columns as $column) {
            if ($column['cast'] !== 'string') {
                $casts[$column['name']] = $column['cast'];
            }
        }

        if ($hasTimestamps) {
            $casts['created_at'] = 'datetime';
            $casts['updated_at'] = 'datetime';
        }

        return $casts;
    }

    public static function inferHidden(array $columns): array
    {
        $hidden = [];

        foreach ($columns as $column) {
            foreach (self::HIDDEN_PATTERNS as $pattern) {
                if (preg_match($pattern, $column['name'])) {
                    $hidden[] = $column['name'];

                    break;
                }
            }
        }

        return $hidden;
    }

    public static function inferRelationships(array $columns, array $foreignKeys): array
    {
        $relationships = [];
        $fkColumns = array_column($foreignKeys, 'column');

        foreach ($foreignKeys as $fk) {
            $relationships[] = [
                'type' => 'belongsTo',
                'column' => $fk['column'],
                'table' => $fk['on'],
                'model' => self::tableToModelName($fk['on']),
                'confirmed' => true,
            ];
        }

        foreach ($columns as $column) {
            if (str_ends_with($column['name'], '_id') && ! in_array($column['name'], $fkColumns, true)) {
                $table = str_replace('_id', '', $column['name']);
                $relationships[] = [
                    'type' => 'belongsTo',
                    'column' => $column['name'],
                    'table' => $table,
                    'model' => self::tableToModelName($table),
                    'confirmed' => false,
                ];
            }
        }

        return $relationships;
    }

    public static function inferScopes(array $columns): array
    {
        $scopes = [];

        foreach ($columns as $column) {
            if ($column['cast'] === 'boolean') {
                $baseName = preg_replace('/^is_/', '', $column['name']);
                $scopeName = ucfirst(self::snakeToCamel($baseName));
                $negatedName = 'Not' . $scopeName;

                $scopes[] = [
                    'name' => $scopeName,
                    'column' => $column['name'],
                    'value' => true,
                    'type' => 'boolean',
                ];
                $scopes[] = [
                    'name' => $negatedName,
                    'column' => $column['name'],
                    'value' => false,
                    'type' => 'boolean',
                ];
            }

            if ($column['type'] === 'enum') {
                $scopeName = 'Where' . ucfirst(self::snakeToCamel($column['name']));
                $scopes[] = [
                    'name' => $scopeName,
                    'column' => $column['name'],
                    'type' => 'enum',
                ];
            }
        }

        return $scopes;
    }

    public static function inferProperties(array $columns, bool $hasTimestamps, bool $hasSoftDeletes): array
    {
        $properties = [['name' => 'id', 'type' => 'int', 'nullable' => false]];

        foreach ($columns as $column) {
            $properties[] = [
                'name' => $column['name'],
                'type' => self::castToPhpDocType($column['cast']),
                'nullable' => $column['nullable'],
            ];
        }

        if ($hasTimestamps) {
            $properties[] = ['name' => 'created_at', 'type' => 'DateTimeHelper', 'nullable' => false];
            $properties[] = ['name' => 'updated_at', 'type' => 'DateTimeHelper', 'nullable' => true];
        }

        if ($hasSoftDeletes) {
            $properties[] = ['name' => 'deleted_at', 'type' => 'DateTimeHelper', 'nullable' => true];
        }

        return $properties;
    }

    private static function extractDefault(string $chainedCalls): mixed
    {
        if (preg_match('/->default\s*\(\s*(.+?)\s*\)/', $chainedCalls, $match)) {
            $val = trim($match[1], " \t\n\r\0\x0B'\"");

            if ($val === 'true') {
                return true;
            }
            if ($val === 'false') {
                return false;
            }
            if ($val === 'null') {
                return null;
            }
            if (is_numeric($val)) {
                return str_contains($val, '.') ? (float) $val : (int) $val;
            }

            return $val;
        }

        return null;
    }

    private static function tableToModelName(string $table): string
    {
        $singular = preg_replace('/s$/', '', $table);

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $singular)));
    }

    private static function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }

    private static function castToPhpDocType(string $cast): string
    {
        return match ($cast) {
            'int' => 'int',
            'float' => 'float',
            'boolean' => 'bool',
            'datetime', 'date' => 'DateTimeHelper',
            'array' => 'array',
            default => 'string',
        };
    }
}
