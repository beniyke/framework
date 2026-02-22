<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class AzureBlobAdapter implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Storage\Adapters;

use Helpers\File\Storage\StorageAdapter;
use RuntimeException;

class AzureBlobAdapter extends StorageAdapter
{
    protected string $accountName;

    protected string $accountKey;

    protected string $container;

    protected string $endpoint;

    protected string $prefix;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->accountName = $config['name'] ?? '';
        $this->accountKey = $config['key'] ?? '';
        $this->container = $config['container'] ?? '';
        $this->endpoint = $config['endpoint'] ?? "https://{$this->accountName}.blob.core.windows.net";
        $this->prefix = rtrim($config['prefix'] ?? '', '/');
    }

    public function exists(string $path): bool
    {
        try {
            $this->request('HEAD', $path);

            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    public function get(string $path): string
    {
        return $this->request('GET', $path);
    }

    public function put(string $path, string $contents, array $options = []): bool
    {
        try {
            $this->request('PUT', $path, $contents, [
                'x-ms-blob-type' => 'BlockBlob',
                'x-ms-blob-content-type' => $this->mimeType($path),
            ]);

            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    public function delete(string $path): bool
    {
        try {
            $this->request('DELETE', $path);

            return true;
        } catch (RuntimeException $e) {
            // Azure returns 404 if not found, we can consider that deleted or return false
            return false;
        }
    }

    public function copy(string $from, string $to): bool
    {
        // Copy requires absolute URL of source
        $sourceUrl = $this->url($from);

        try {
            $this->request('PUT', $to, '', [
                'x-ms-copy-source' => $sourceUrl,
            ]);

            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    public function move(string $from, string $to): bool
    {
        if ($this->copy($from, $to)) {
            return $this->delete($from);
        }

        return false;
    }

    public function size(string $path): int
    {
        $headers = $this->getBlobProperties($path);

        return (int) ($headers['content-length'] ?? 0);
    }

    public function lastModified(string $path): int
    {
        $headers = $this->getBlobProperties($path);

        return isset($headers['last-modified']) ? strtotime($headers['last-modified']) : 0;
    }

    protected function getBlobProperties(string $path): array
    {
        /** @var array<string> $responseHeaders */
        $responseHeaders = [];
        try {
            $this->request('HEAD', $path, '', [], ['response_headers' => &$responseHeaders]);
        } catch (RuntimeException $e) {
            return [];
        }

        // Parse headers
        $headers = [];
        foreach ($responseHeaders as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return $headers;
    }

    public function mimeType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mimes = [
            'txt' => 'text/plain',
            'json' => 'application/json',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'xml' => 'application/xml',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
        ];

        return $mimes[$extension] ?? 'application/octet-stream';
    }

    public function url(string $path): string
    {
        return rtrim($this->endpoint, '/') . '/' . $this->container . '/' . ltrim($this->applyPrefix($path), '/');
    }

    public function temporaryUrl(string $path, int $expiration, array $options = []): string
    {
        $path = $this->applyPrefix($path);

        $now = time();
        if ($expiration > $now) {
            $expiry = $expiration; // It's a timestamp
        } else {
            $expiry = $now + $expiration; // It's a duration
        }

        $startTime = gmdate('Y-m-d\TH:i:s\Z', $now - 300); // Start 5 mins ago for clock skew
        $expiryTime = gmdate('Y-m-d\TH:i:s\Z', $expiry);

        // Service SAS parameters
        $permissions = 'r'; // Read only
        $version = '2019-12-12';
        $resource = 'b'; // Blob

        // Canonicalized Resource: /blob/account/container/blobname
        $canonicalResource = "/blob/{$this->accountName}/{$this->container}/" . ltrim($path, '/');

        $stringToSign = $permissions . "\n" .
            $startTime . "\n" .
            $expiryTime . "\n" .
            $canonicalResource . "\n" .
            "" . "\n" . // Identifier
            "" . "\n" . // IP
            "https" . "\n" . // Protocol
            $version . "\n" .
            $resource . "\n" .
            "" . "\n" . // Snapshot
            "" . "\n" . // rscc (Cache-Control)
            "" . "\n" . // rscd (Content-Disposition)
            "" . "\n" . // rsce (Content-Encoding)
            "" . "\n" . // rscl (Content-Language)
            "";         // rsct (Content-Type)

        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true));

        $queryParams = [
            'sv' => $version,
            'st' => $startTime,
            'se' => $expiryTime,
            'sr' => $resource,
            'sp' => $permissions,
            'spr' => 'https',
            'sig' => $signature,
        ];

        $url = rtrim($this->endpoint, '/') . '/' . $this->container . '/' . ltrim($path, '/');

        return $url . '?' . http_build_query($queryParams);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $prefix = $this->applyPrefix($directory);
        // Ensure prefix ends with / if it's a directory we are listing, but strictly speaking Azure prefix is just a string match.
        if ($prefix !== '') {
            $prefix = rtrim($prefix, '/') . '/';
        }

        $files = [];
        $marker = null;

        do {
            $queryParams = [
                'restype' => 'container',
                'comp' => 'list',
            ];

            if ($prefix) {
                $queryParams['prefix'] = $prefix;
            }
            if (!$recursive) {
                $queryParams['delimiter'] = '/';
            }
            if ($marker) {
                $queryParams['marker'] = $marker;
            }

            // GET request with query params
            $queryString = http_build_query($queryParams);

            try {
                $response = $this->request('GET', "?" . $queryString);
            } catch (RuntimeException $e) {
                return [];
            }

            $xml = simplexml_load_string($response);

            if ($xml === false) {
                break;
            }

            if (isset($xml->Blobs->Blob)) {
                foreach ($xml->Blobs->Blob as $blob) {
                    $name = (string)$blob->Name;
                    if ($name === $prefix) {
                        continue;
                    }
                    $files[] = $this->removePrefix($name);
                }
            }

            if (!$recursive && isset($xml->Blobs->BlobPrefix)) {
                foreach ($xml->Blobs->BlobPrefix as $blobPrefix) {
                    $files[] = $this->removePrefix((string)$blobPrefix->Name);
                }
            }

            $marker = (string)$xml->NextMarker;
        } while ($marker);

        return $files;
    }

    public function makeDirectory(string $path): bool
    {
        // Create a 0-byte blob with a trailing slash to simulate a directory
        return $this->put(rtrim($path, '/') . '/', '');
    }

    public function readStream(string $path, array $options = []): mixed
    {
        $stream = fopen('php://temp', 'w+');
        $headers = [];

        if (isset($options['start']) && isset($options['end'])) {
            $headers['x-ms-range'] = "bytes={$options['start']}-{$options['end']}";
        }

        try {
            $this->request('GET', $path, '', $headers, ['sink' => $stream]);
            rewind($stream);

            return $stream;
        } catch (RuntimeException $e) {
            fclose($stream);

            return null;
        }
    }

    public function writeStream(string $path, $resource, array $options = []): bool
    {
        try {
            $this->request('PUT', $path, $resource, [
                'x-ms-blob-type' => 'BlockBlob',
                'x-ms-blob-content-type' => $this->mimeType($path),
            ]);

            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    public function deleteDirectory(string $path): bool
    {
        // Recursive list and delete
        $files = $this->files($path, true);
        foreach ($files as $file) {
            $fullPath = rtrim($path, '/') . '/' . $file;
            $this->delete($fullPath);
        }

        // Also try to delete the directory marker itself if it exists
        $this->delete(rtrim($path, '/') . '/');

        return true;
    }

    protected function request(string $method, string $path, mixed $payload = '', array $headers = [], array $options = []): string
    {
        $responseHeaders = &$options['response_headers'];
        if (!is_array($responseHeaders)) {
            $dummy = [];
            $responseHeaders = &$dummy;
        }

        $pathWithoutQuery = $path;
        $queryString = '';
        if (str_contains($path, '?')) {
            [$pathWithoutQuery, $queryString] = explode('?', $path, 2);
        }

        if ($pathWithoutQuery !== '') {
            $pathWithoutQuery = $this->applyPrefix($pathWithoutQuery);
        }

        // /{account}/{container}/{blobName}
        $canonicalResource = "/{$this->accountName}/{$this->container}";
        if ($pathWithoutQuery !== '') {
            $canonicalResource .= '/' . ltrim($pathWithoutQuery, '/');
        } else {
            // Container level
        }

        $queryParams = [];
        if ($queryString) {
            parse_str($queryString, $queryParams);
            // Lowercase keys
            $lowerParams = [];
            foreach ($queryParams as $k => $v) {
                $lowerParams[strtolower($k)] = $v;
            }
            ksort($lowerParams);

            foreach ($lowerParams as $k => $v) {
                $canonicalResource .= "\n{$k}:{$v}";
            }
        }

        $date = gmdate('D, d M Y H:i:s T', time());
        $version = '2019-12-12';

        $headers = array_merge($headers, [
            'x-ms-date' => $date,
            'x-ms-version' => $version,
        ]);

        // Handle Payload Size
        if (is_resource($payload)) {
            if (isset($options['size'])) {
                $headers['Content-Length'] = (string)$options['size'];
            } else {
                $fstat = fstat($payload);
                $headers['Content-Length'] = (string)($fstat['size'] ?? 0);
            }
        } else {
            $headers['Content-Length'] = (string)strlen((string)$payload);
        }

        $contentType = $headers['x-ms-blob-content-type'] ?? ($headers['Content-Type'] ?? '');

        $canonicalizedHeaders = '';
        $msHeaders = [];
        foreach ($headers as $k => $v) {
            if (str_starts_with(strtolower($k), 'x-ms-')) {
                $msHeaders[strtolower($k)] = trim((string)$v);
            }
        }
        ksort($msHeaders);
        foreach ($msHeaders as $k => $v) {
            $canonicalizedHeaders .= "$k:$v\n";
        }

        $cl = (string) $headers['Content-Length'];

        $stringToSign = "$method\n\n\n$cl\n\n$contentType\n\n\n\n\n\n\n" .
            $canonicalizedHeaders .
            $canonicalResource;

        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true));

        $headers['Authorization'] = "SharedKey {$this->accountName}:$signature";

        $uri = '/' . $this->container;
        if ($pathWithoutQuery !== '') {
            $uri .= '/' . ltrim($pathWithoutQuery, '/');
        }
        $url = rtrim($this->endpoint, '/') . $uri;
        if ($queryString) {
            $url .= '?' . $queryString;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if (is_resource($payload)) {
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_INFILE, $payload);
            curl_setopt($ch, CURLOPT_INFILESIZE, (int)$headers['Content-Length']);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $httpHeaders = [];
        foreach ($headers as $k => $v) {
            $httpHeaders[] = "$k: $v";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $responseHeaders[] = trim($header);

            return $len;
        });

        // Response Sink
        if (isset($options['sink']) && is_resource($options['sink'])) {
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) use ($options) {
                return fwrite($options['sink'], $data);
            });
        }

        // Robust SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // Retry Loop
        $response = $this->retry(function () use ($ch) {
            $result = curl_exec($ch);
            if ($result === false) {
                throw new RuntimeException(curl_error($ch));
            }

            return $result;
        });

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new RuntimeException("Azure Error: $httpCode $method $url\nResp: $response");
        }

        return (string) $response;
    }

    protected function applyPrefix(string $path): string
    {
        $path = $this->normalizePath($path);

        return $this->prefix ? $this->prefix . '/' . ltrim($path, '/') : $path;
    }

    protected function removePrefix(string $path): string
    {
        if ($this->prefix && str_starts_with($path, $this->prefix . '/')) {
            return substr($path, strlen($this->prefix) + 1);
        }

        return $path;
    }
}
