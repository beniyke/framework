<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * ConfigCollector collects configuration data for the DebugBar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Collector;

use Core\Services\ConfigServiceInterface;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class ConfigCollector extends DataCollector implements Renderable
{
    protected ConfigServiceInterface $config;

    public function __construct(ConfigServiceInterface $config)
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'config';
    }

    public function collect(): array
    {
        $allConfig = $this->config->all();
        $sanitized = $this->sanitizeConfig($allConfig);

        return [
            'app_name' => $this->config->get('name', 'Unknown'),
            'environment' => $this->config->get('env', 'production'),
            'debug' => $this->config->isDebugEnabled(),
            'timezone' => $this->config->get('timezone', 'UTC'),
            'all_config' => array_map(function ($v) {
                $encoded = json_encode($v, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);

                return $encoded === false ? 'JSON Error: ' . json_last_error_msg() : $encoded;
            }, $sanitized),
            'config_count' => count($allConfig),
        ];
    }

    private function sanitizeConfig(array $config): array
    {
        $sensitiveKeys = [
            'password',
            'secret',
            'key',
            'token',
            'api_key',
            'private_key',
            'db_password',
            'database_password',
            'mail_password',
            'smtp_password',
        ];

        foreach ($config as $key => $value) {
            $lowerKey = strtolower((string) $key);

            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $config[$key] = '***HIDDEN***';

                    continue 2;
                }
            }

            if (is_array($value)) {
                $config[$key] = $this->sanitizeConfig($value);
            }
        }

        return $config;
    }

    public function getWidgets(): array
    {
        return [
            'Config' => [
                'icon' => 'cog',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'config.all_config',
                'default' => '{}',
            ],
            'Config:badge' => [
                'map' => 'config.environment',
                'default' => '\'production\'',
            ],
        ];
    }
}
