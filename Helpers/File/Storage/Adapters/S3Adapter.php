<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class S3Adapter implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Storage\Adapters;

use Helpers\File\Storage\StorageAdapter;
use RuntimeException;

class S3Adapter extends StorageAdapter
{
    protected string $key;

    protected string $secret;

    protected string $region;

    protected string $bucket;

    protected string $url;

    protected string $endpoint;

    protected string $prefix;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->key = $config['key'] ?? '';
        $this->secret = $config['secret'] ?? '';
        $this->region = $config['region'] ?? 'us-east-1';
        $this->bucket = $config['bucket'] ?? '';
        $this->url = $config['url'] ?? '';
        $this->endpoint = $config['endpoint'] ?? "https://s3.{$this->region}.amazonaws.com";
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
                'Content-Type' => $this->mimeType($path),
                'x-amz-acl' => $options['visibility'] ?? 'private',
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
            return false;
        }
    }

    public function copy(string $from, string $to): bool
    {
        $source = "/{$this->bucket}/" . $this->applyPrefix($from);

        try {
            $this->request('PUT', $to, '', [
                'x-amz-copy-source' => $source,
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
        try {
            $headers = $this->head($path);

            return (int) ($headers['content-length'] ?? 0);
        } catch (RuntimeException $e) {
            return 0;
        }
    }

    public function lastModified(string $path): int
    {
        try {
            $headers = $this->head($path);

            return isset($headers['last-modified']) ? strtotime($headers['last-modified']) : 0;
        } catch (RuntimeException $e) {
            return 0;
        }
    }

    public function mimeType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mimes = [
            'txt' => 'text/plain',
            'html' => 'text/html',
            'json' => 'application/json',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
        ];

        return $mimes[$extension] ?? 'application/octet-stream';
    }

    public function url(string $path): string
    {
        if ($this->url) {
            return rtrim($this->url, '/') . '/' . ltrim($this->applyPrefix($path), '/');
        }

        return rtrim($this->endpoint, '/') . '/' . $this->bucket . '/' . ltrim($this->applyPrefix($path), '/');
    }

    public function temporaryUrl(string $path, int $expiration, array $options = []): string
    {
        $path = $this->applyPrefix($path);

        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $uri = '/' . $this->bucket . '/' . ltrim($path, '/');

        /**
         * Expiration Logic: X-Amz-Expires is seconds from now, but strict S3 uses duration.
         * The duration in X-Amz-Expires is passed.
         * Current timestamp is X-Amz-Date.
         */

        $timestamp = time();
        $longDate = gmdate('Ymd\THis\Z', $timestamp);
        $shortDate = gmdate('Ymd', $timestamp);
        $expirationSeconds = $expiration - time(); // If absolute time passed
        if ($expiration > $timestamp) {
            $duration = $expiration - $timestamp;
        } else {
            $duration = $expiration;
        }

        $credentialScope = "$shortDate/{$this->region}/s3/aws4_request";

        // Query Params for Presigning
        $queryParams = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->key . '/' . $credentialScope,
            'X-Amz-Date' => $longDate,
            'X-Amz-Expires' => (string)$duration,
            'X-Amz-SignedHeaders' => 'host',
        ];

        // Handle specific download behavior overrides
        if (isset($options['ResponseContentDisposition'])) {
            $queryParams['response-content-disposition'] = $options['ResponseContentDisposition'];
        }
        if (isset($options['ResponseContentType'])) {
            $queryParams['response-content-type'] = $options['ResponseContentType'];
        }

        ksort($queryParams);

        $canonicalQueryString = [];
        foreach ($queryParams as $key => $value) {
            $canonicalQueryString[] = rawurlencode($key) . '=' . rawurlencode((string)$value);
        }
        $canonicalQueryStringStr = implode('&', $canonicalQueryString);

        /**
         * Canonical Request
         * METHOD
         * URI
         * QUERY
         * HEADERS
         * SIGNED HEADERS
         * PAYLOAD HASH (UNSIGNED-PAYLOAD)
         */

        $canonicalHeaders = "host:{$host}\n";
        $signedHeaders = "host";
        $payloadHash = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = "GET\n$uri\n$canonicalQueryStringStr\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
        $stringToSign = "AWS4-HMAC-SHA256\n$longDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        // Signature
        $kSecret = 'AWS4' . $this->secret;
        $kDate = hash_hmac('sha256', $shortDate, $kSecret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $finalUrl = rtrim($this->endpoint, '/') . $uri . '?' . $canonicalQueryStringStr . '&X-Amz-Signature=' . $signature;

        return $finalUrl;
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        return $this->listFiles($directory, $recursive);
    }

    public function readStream(string $path, array $options = []): mixed
    {
        $stream = fopen('php://temp', 'w+');
        $headers = [];

        if (isset($options['start']) && isset($options['end'])) {
            $headers['Range'] = "bytes={$options['start']}-{$options['end']}";
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
            $headers = [
                'Content-Type' => $this->mimeType($path),
                'x-amz-acl' => $options['visibility'] ?? 'private',
            ];

            $this->request('PUT', $path, $resource, $headers);

            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    protected function listFiles(string $directory, bool $recursive, ?string $continuationToken = null): array
    {
        $directory = $this->applyPrefix($directory);
        $directory = $directory ? rtrim($directory, '/') . '/' : '';

        $files = [];

        do {
            $query = [
                'list-type' => '2',
                'prefix' => $directory,
            ];

            if (!$recursive) {
                $query['delimiter'] = '/';
            }

            if ($continuationToken) {
                $query['continuation-token'] = $continuationToken;
            }

            $queryString = http_build_query($query);
            $response = $this->request('GET', "?" . $queryString);

            $xml = simplexml_load_string($response);

            if ($xml === false) {
                break;
            }

            if (isset($xml->Contents)) {
                foreach ($xml->Contents as $item) {
                    $key = (string)$item->Key;
                    if ($key === $directory) {
                        continue;
                    }
                    $files[] = $this->removePrefix($key);
                }
            }

            if (!$recursive && isset($xml->CommonPrefixes)) {
                foreach ($xml->CommonPrefixes as $prefix) {
                    $files[] = $this->removePrefix((string)$prefix->Prefix);
                }
            }

            $continuationToken = isset($xml->NextContinuationToken) ? (string)$xml->NextContinuationToken : null;
            $isTruncated = isset($xml->IsTruncated) && (string)$xml->IsTruncated === 'true';
        } while ($isTruncated && $continuationToken);

        return $files;
    }

    public function makeDirectory(string $path): bool
    {
        return $this->put(rtrim($path, '/') . '/', '');
    }

    public function deleteDirectory(string $path): bool
    {
        $prefix = $this->applyPrefix($path);
        $prefix = $prefix ? rtrim($prefix, '/') . '/' : '';

        $continuationToken = null;

        do {
            $query = [
                'list-type' => '2',
                'prefix' => $prefix,
            ];

            if ($continuationToken) {
                $query['continuation-token'] = $continuationToken;
            }

            $queryString = http_build_query($query);
            $response = $this->request('GET', "?" . $queryString);
            $xml = simplexml_load_string($response);

            if ($xml === false || !isset($xml->Contents)) {
                break;
            }

            $keysToDelete = [];
            foreach ($xml->Contents as $content) {
                $keysToDelete[] = (string)$content->Key;
            }

            if (!empty($keysToDelete)) {
                $chunks = array_chunk($keysToDelete, 1000);
                foreach ($chunks as $chunk) {
                    $payload = '<?xml version="1.0" encoding="UTF-8"?><Delete><Quiet>true</Quiet>';
                    foreach ($chunk as $key) {
                        $payload .= '<Object><Key>' . htmlspecialchars($key) . '</Key></Object>';
                    }
                    $payload .= '</Delete>';

                    $this->request('POST', '?delete', $payload, [
                        'Content-MD5' => base64_encode(md5($payload, true)),
                        'Content-Type' => 'application/xml'
                    ]);
                }
            }

            $continuationToken = isset($xml->NextContinuationToken) ? (string)$xml->NextContinuationToken : null;
            $isTruncated = isset($xml->IsTruncated) && (string)$xml->IsTruncated === 'true';
        } while ($isTruncated && $continuationToken);

        return true;
    }

    protected function head(string $path): array
    {
        $responseHeaders = [];
        $this->request('HEAD', $path, '', [], ['response_headers' => &$responseHeaders]);

        $headers = [];
        /** @var array<string> $responseHeaders */
        foreach ($responseHeaders as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return $headers;
    }

    /**
     * Perform a signed request to S3.
     *
     * @param string $method
     * @param string $path
     * @param mixed  $payload String or Resource
     * @param array  $headers
     * @param array  $options ['sink' => resource, 'response_headers' => &array]
     */
    protected function request(string $method, string $path, mixed $payload = '', array $headers = [], array $options = []): string
    {
        $responseHeaders = &$options['response_headers'];
        if (!is_array($responseHeaders)) {
            $responseHeaders = [];
        }

        $pathWithoutQuery = $path;
        $queryString = '';

        if (str_contains($path, '?')) {
            [$pathWithoutQuery, $queryString] = explode('?', $path, 2);
        }

        if ($pathWithoutQuery !== '' && $pathWithoutQuery !== '/') {
            $pathWithoutQuery = $this->applyPrefix($pathWithoutQuery);
        }

        $uri = '/' . $this->bucket . '/' . ltrim($pathWithoutQuery, '/');

        $canonicalQueryString = '';
        if ($queryString) {
            parse_str($queryString, $queryParams);
            ksort($queryParams);
            $codedParams = [];
            foreach ($queryParams as $k => $v) {
                $codedParams[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
            }
            $canonicalQueryString = implode('&', $codedParams);
            $queryString = http_build_query($queryParams);
        }

        $url = rtrim($this->endpoint, '/') . $uri;
        if ($queryString) {
            $url .= '?' . $queryString;
        }

        $host = parse_url($this->endpoint, PHP_URL_HOST);

        $timestamp = time();
        $dateLong = gmdate('Ymd\THis\Z', $timestamp);
        $dateShort = gmdate('Ymd', $timestamp);

        if (is_resource($payload)) {
            $payloadHash = 'UNSIGNED-PAYLOAD'; // Optimization for streams
        } else {
            $payloadHash = hash('sha256', (string)$payload);
        }

        $headers = array_merge($headers, [
            'Host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $dateLong,
        ]);

        ksort($headers);

        $canonicalHeaders = '';
        $signedHeaders = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            $canonicalHeaders .= $lowerKey . ':' . trim((string)$value) . "\n";
            $signedHeaders[] = $lowerKey;
        }
        $signedHeadersStr = implode(';', $signedHeaders);

        $canonicalRequest = "$method\n$uri\n$canonicalQueryString\n$canonicalHeaders\n$signedHeadersStr\n$payloadHash";

        $credentialScope = "$dateShort/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n$dateLong\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $kSecret = 'AWS4' . $this->secret;
        $kDate = hash_hmac('sha256', $dateShort, $kSecret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$this->key}/$credentialScope, SignedHeaders=$signedHeadersStr, Signature=$signature";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Handle Payload (String vs Stream)
        if (is_resource($payload)) {
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_INFILE, $payload);
            // Optionally set INFILESIZE if known, but not strictly required if chunked
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));

        // Response Header Callback
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $responseHeaders[] = trim($header);

            return $len;
        });

        // Response Body Streaming (Sink)
        if (isset($options['sink']) && is_resource($options['sink'])) {
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) use ($options) {
                return fwrite($options['sink'], $data);
            });
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // default

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
            throw new RuntimeException("S3 Error: $httpCode $method $uri");
        }

        return (string) $response;
    }

    protected function formatHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $out[] = "$k: $v";
        }

        return $out;
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
