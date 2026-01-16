<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * EnvironmentCollector collects environment variables for the DebugBar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Collector;

use Core\Support\Adapters\Interfaces\EnvironmentInterface;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class EnvironmentCollector extends DataCollector implements Renderable
{
    protected EnvironmentInterface $environment;

    public function __construct(EnvironmentInterface $environment)
    {
        $this->environment = $environment;
    }

    public function getName(): string
    {
        return 'environment';
    }

    public function collect(): array
    {
        $envVars = $_ENV;
        $sanitized = $this->sanitizeEnvVars($envVars);

        return [
            'is_local' => $this->environment->isLocal(),
            'is_production' => $this->environment->isProduction(),
            'is_testing' => $this->environment->isTesting(),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
            'env_vars' => json_encode(array_map(function ($v) {
                if (is_array($v) || is_object($v)) {
                    return $v; // json_encode will handle it in the outer call
                }

                return $v;
            }, $sanitized), JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE),
            'env_count' => count($envVars),
        ];
    }

    private function sanitizeEnvVars(array $vars): array
    {
        $sensitivePatterns = [
            'PASSWORD',
            'SECRET',
            'KEY',
            'TOKEN',
            'API',
            'PRIVATE',
            'DB_PASS',
            'MAIL_PASS',
            'SMTP_PASS',
            'AWS_SECRET',
        ];

        foreach ($vars as $key => $value) {
            $upperKey = strtoupper($key);

            foreach ($sensitivePatterns as $pattern) {
                if (str_contains($upperKey, $pattern)) {
                    $vars[$key] = '***HIDDEN***';

                    continue 2;
                }
            }
        }

        return $vars;
    }

    public function getWidgets(): array
    {
        return [
            'Environment' => [
                'icon' => 'server',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'environment',
                'default' => '{}',
            ],
            'Environment:badge' => [
                'map' => 'environment.php_version',
                'default' => '\'PHP\'',
            ],
        ];
    }
}
