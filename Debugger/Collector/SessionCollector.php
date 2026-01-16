<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * SessionCollector collects session data for the DebugBar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Helpers\Http\Session;

class SessionCollector extends DataCollector implements Renderable
{
    protected Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function getName(): string
    {
        return 'session';
    }

    public function collect(): array
    {
        $sessionData = $this->session->all();
        $sanitized = $this->sanitizeSessionData($sessionData);
        $flashData = $this->session->get('flash', []);

        return [
            'id' => $this->session->getId(),
            'data' => $sanitized,
            'count' => count($sessionData),
            'flash_data' => $flashData,
            'has_flash' => ! empty($flashData),
        ];
    }

    private function sanitizeSessionData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'private_key', 'csrf_token'];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '***HIDDEN***';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeSessionData($value);
            }
        }

        return $data;
    }

    public function getWidgets(): array
    {
        return [
            'Session' => [
                'icon' => 'archive',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'session.data',
                'default' => '{}',
            ],
            'Session:badge' => [
                'map' => 'session.count',
                'default' => '0',
            ],
        ];
    }
}
