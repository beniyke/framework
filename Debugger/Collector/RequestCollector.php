<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * RequestCollector collects HTTP request data for the DebugBar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Helpers\Http\Request;

class RequestCollector extends DataCollector implements Renderable
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getName(): string
    {
        return 'http_request';
    }

    public function collect(): array
    {
        $server = $this->request->server();

        return [
            'method' => $this->request->method(),
            'uri' => $this->request->uri(),
            'base_url' => $this->request->baseUrl(),
            'full_route' => $this->request->fullRoute(),
            'path' => $this->request->path(),
            'host' => $this->request->host(),
            'scheme' => $this->request->scheme(),
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'is_ajax' => $this->request->isAjax(),
            'is_secure' => $this->request->secure(),
            'is_bot' => $this->request->isBot(),
            'protocol' => $this->request->server('SERVER_PROTOCOL'),
            'query_params' => $this->safeJsonEncode($this->request->get() ?? []),
            'post_data' => $this->safeJsonEncode($this->sanitizePostData($this->request->post() ?? [])),
            'headers' => $this->safeJsonEncode($this->getHeaders()),
            'cookies' => $this->safeJsonEncode($this->request->cookies() ?? []),
            'files' => $this->safeJsonEncode($this->getFileInfo()),
            'content_type' => $this->request->header('Content-Type'),
            'accept' => $this->request->header('Accept'),
            'referer' => $this->request->referer(),
        ];
    }

    private function sanitizePostData(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'secret', 'api_key', 'private_key'];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '***HIDDEN***';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizePostData($value);
            }
        }

        return $data;
    }

    private function getHeaders(): array
    {
        $headers = [];
        $server = $this->request->getServer();

        foreach ($server->all() as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    private function getFileInfo(): array
    {
        $files = [];
        $fileData = $this->request->file();

        if (! is_array($fileData)) {
            return $files;
        }

        foreach ($fileData as $key => $file) {
            if (is_array($file)) {
                if (isset($file['name'])) {
                    $files[$key] = [
                        'name' => $file['name'] ?? 'unknown',
                        'size' => isset($file['size']) ? $this->formatFileSize($file['size']) : 'unknown',
                        'type' => $file['type'] ?? 'unknown',
                    ];
                } else {
                    $files[$key] = 'Multiple files';
                }
            }
        }

        return $files;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / 1048576, 2) . ' MB';
        }
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
            'HTTP Request' => [
                'icon' => 'arrow-right',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'http_request',
                'default' => '{}',
            ],
            'HTTP Request:badge' => [
                'map' => 'http_request.method',
                'default' => '\'GET\'',
            ],
        ];
    }
}
