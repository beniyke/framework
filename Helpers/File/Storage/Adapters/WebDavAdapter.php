<?php

declare(strict_types=1);

namespace Helpers\File\Storage\Adapters;

use Helpers\File\Storage\StorageAdapter;
use RuntimeException;

class WebDavAdapter extends StorageAdapter
{
    protected string $baseUri;

    protected string $username;

    protected string $password;

    protected string $authType;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->baseUri = rtrim($config['baseUri'] ?? '', '/');
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->authType = $config['authType'] ?? 'basic';

        if (empty($this->baseUri)) {
            throw new RuntimeException('WebDAV storage requires a [baseUri].');
        }
    }

    protected function request(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        $url = $this->baseUri . '/' . ltrim($this->normalizePath($path), '/');
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($this->username) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        curl_close($ch);

        return [
            'code' => $httpCode,
            'header' => $header,
            'body' => $body,
        ];
    }

    public function exists(string $path): bool
    {
        $response = $this->request('PROPFIND', $path, null, ['Depth: 0']);

        return $response['code'] >= 200 && $response['code'] < 300;
    }

    public function get(string $path): string
    {
        $response = $this->request('GET', $path);

        return $response['code'] === 200 ? $response['body'] : '';
    }

    public function put(string $path, string $contents, array $options = []): bool
    {
        $response = $this->request('PUT', $path, $contents);

        return $response['code'] >= 200 && $response['code'] < 300;
    }

    public function delete(string $path): bool
    {
        $response = $this->request('DELETE', $path);

        return $response['code'] >= 200 && $response['code'] < 300;
    }

    public function copy(string $from, string $to): bool
    {
        $destination = $this->baseUri . '/' . ltrim($this->normalizePath($to), '/');
        $response = $this->request('COPY', $from, null, ["Destination: {$destination}", "Overwrite: T"]);

        return $response['code'] >= 200 && $response['code'] < 300;
    }

    public function move(string $from, string $to): bool
    {
        $destination = $this->baseUri . '/' . ltrim($this->normalizePath($to), '/');
        $response = $this->request('MOVE', $from, null, ["Destination: {$destination}", "Overwrite: T"]);

        return $response['code'] >= 200 && $response['code'] < 300;
    }

    public function size(string $path): int
    {
        $response = $this->request('PROPFIND', $path, null, ['Depth: 0']);

        if ($response['code'] < 200 || $response['code'] >= 300) {
            return 0;
        }

        if (preg_match('/<[^>]*getcontentlength[^>]*>([^<]+)<\//i', $response['body'], $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    public function lastModified(string $path): int
    {
        $response = $this->request('PROPFIND', $path, null, ['Depth: 0']);

        if ($response['code'] < 200 || $response['code'] >= 300) {
            return 0;
        }

        if (preg_match('/<[^>]*getlastmodified[^>]*>([^<]+)</i', $response['body'], $matches)) {
            return strtotime($matches[1]);
        }

        return 0;
    }

    public function mimeType(string $path): string
    {
        return 'application/octet-stream';
    }

    public function url(string $path): string
    {
        return $this->baseUri . '/' . ltrim($this->normalizePath($path), '/');
    }

    public function temporaryUrl(string $path, int $expiration, array $options = []): string
    {
        return $this->url($path);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        // PROPFIND with Depth: 1
        return [];
    }

    public function makeDirectory(string $path): bool
    {
        $response = $this->request('MKCOL', $path);

        return $response['code'] >= 200 && $response['code'] < 300;
    }

    public function deleteDirectory(string $path): bool
    {
        return $this->delete($path);
    }
}
