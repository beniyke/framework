<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * ResponseCollector collects HTTP response data for the DebugBar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Helpers\Http\Response;

class ResponseCollector extends DataCollector implements Renderable
{
    protected ?Response $response = null;

    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }

    public function getName(): string
    {
        return 'response';
    }

    public function collect(): array
    {
        if (! $this->response) {
            return [
                'status' => 'Not set',
                'headers' => [],
                'content_type' => 'Unknown',
            ];
        }

        return [
            'status' => $this->response->getStatusCode(),
            'status_text' => $this->getStatusText($this->response->getStatusCode()),
            'headers' => $this->safeJsonEncode($this->response->getHeaders()),
            'content_type' => $this->response->getHeader('Content-Type') ?? 'text/html',
            'content_length' => $this->response->getHeader('Content-Length') ?? 'Unknown',
        ];
    }

    private function getStatusText(int $code): string
    {
        return $this->response->reason($code);
    }

    private function safeJsonEncode(mixed $data): string
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            return 'JSON Error: ' . json_last_error_msg();
        }

        return $encoded;
    }

    public function getWidgets(): array
    {
        return [
            'Response' => [
                'icon' => 'arrow-left',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'response',
                'default' => '{}',
            ],
            'Response:badge' => [
                'map' => 'response.status',
                'default' => '200',
            ],
        ];
    }
}
