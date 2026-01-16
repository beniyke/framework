<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * cURL wrapper for HTTP requests.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http\Client;

use CURLFile;
use CurlHandle;
use CurlMultiHandle;
use Helpers\Format\FormatCollection as Format;
use Helpers\Http\Header;
use RuntimeException;
use Throwable;

class Curl
{
    private bool $verifySsl = true;

    private int $timeout = 30000;

    private Header $headers;

    private int $retryCount = 0;

    private int $retryDelayMs = 0;

    private array $queryParams = [];

    private ?string $requestUrl = null;

    private ?string $requestMethod = null;

    private mixed $requestData = null;

    private ?string $authType = null;

    private ?string $authUsername = null;

    private ?string $authPassword = null;

    private ?string $authToken = null;

    private array $fileAttachments = [];

    public function __construct(array $defaultHeaders = [])
    {
        $defaults = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $finalHeaders = array_merge($defaults, $defaultHeaders);

        $this->headers = new Header($finalHeaders);
    }

    public function withQueryParameters(array $params): self
    {
        $this->queryParams = array_merge($this->queryParams, $params);

        return $this;
    }

    public function retry(int $times, int $delayMs = 100): self
    {
        $this->retryCount = max(0, $times);
        $this->retryDelayMs = max(0, $delayMs);

        return $this;
    }

    public function headers(array $headers): self
    {
        $this->headers = new Header($headers);

        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers->set($name, $value);

        return $this;
    }

    public function timeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function withoutSslVerification(): self
    {
        $this->verifySsl = false;

        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): self
    {
        $this->authToken = "{$type} {$token}";
        $this->authType = 'token';

        return $this;
    }

    public function withBasicAuth(string $username, string $password): self
    {
        $this->authUsername = $username;
        $this->authPassword = $password;
        $this->authType = 'basic';

        return $this;
    }

    public function withDigestAuth(string $username, string $password): self
    {
        $this->authUsername = $username;
        $this->authPassword = $password;
        $this->authType = 'digest';

        return $this;
    }

    public function asForm(): self
    {
        $this->headers = $this->headers->set('Content-Type', 'application/x-www-form-urlencoded');

        return $this;
    }

    public function asJson(): self
    {
        $this->headers = $this->headers->set('Content-Type', 'application/json');

        return $this;
    }

    public function asRaw(string $data): self
    {
        $this->requestData = $data;
        if (! $this->headers->get('Content-Type')) {
            $this->headers = $this->headers->set('Content-Type', 'text/plain');
        }

        return $this;
    }

    public function attach(string $name, string $path, ?string $mimeType = null, ?string $fileName = null): self
    {
        if (! file_exists($path)) {
            return $this;
        }

        $this->fileAttachments[$name] = [
            'path' => $path,
            'mime' => $mimeType,
            'name' => $fileName,
        ];

        $this->headers = $this->headers->set('Content-Type', 'multipart/form-data');

        return $this;
    }

    public function get(string $url): self
    {
        $this->requestUrl = $url;
        $this->requestMethod = 'get';
        $this->requestData = null;

        return $this;
    }

    public function post(string $url, mixed $data): self
    {
        $this->requestUrl = $url;
        $this->requestMethod = 'post';
        $this->requestData = $data;

        return $this;
    }

    public function put(string $url, mixed $data): self
    {
        $this->requestUrl = $url;
        $this->requestMethod = 'put';
        $this->requestData = $data;

        return $this;
    }

    public function patch(string $url, mixed $data): self
    {
        $this->requestUrl = $url;
        $this->requestMethod = 'patch';
        $this->requestData = $data;

        return $this;
    }

    public function delete(string $url, mixed $data = null): self
    {
        $this->requestUrl = $url;
        $this->requestMethod = 'delete';
        $this->requestData = $data;

        return $this;
    }

    public function download(string $url, string $destination): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $dir = dirname($destination);
        if (! is_dir($dir) || ! is_writable($dir)) {
            return false;
        }

        $tempDestination = $destination . '.tmp.' . uniqid();

        $this->requestUrl = $url;
        $this->requestMethod = 'get';

        $fp = false;
        $ch = false;

        try {
            $fp = fopen($tempDestination, 'w+');

            if ($fp === false) {
                return false;
            }

            $ch = curl_init();

            if ($ch === false) {
                fclose($fp);

                return false;
            }

            curl_setopt($ch, CURLOPT_URL, $this->_buildUrlWithQuery());
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
            curl_setopt($ch, CURLOPT_FAILONERROR, false);

            // Add auth if configured
            if ($this->authType === 'token' && $this->authToken) {
                $this->headers = $this->headers->set('Authorization', $this->authToken);
            } elseif ($this->authType === 'basic' || $this->authType === 'digest') {
                curl_setopt($ch, CURLOPT_USERPWD, "{$this->authUsername}:{$this->authPassword}");
                if ($this->authType === 'basic') {
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                } elseif ($this->authType === 'digest') {
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                }
            }

            $preparedHeaders = self::_prepareHeaders($this->headers->all());
            curl_setopt($ch, CURLOPT_HTTPHEADER, $preparedHeaders);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // Close resources explicitly
            if (is_resource($ch)) {
                curl_close($ch);
            }
            if (is_resource($fp)) {
                fclose($fp);
            }

            if ($result === false || $httpCode >= 400) {
                // Cleanup temp file on failure
                if (file_exists($tempDestination)) {
                    @unlink($tempDestination);
                }

                return false;
            }

            // On Windows, rename matches POSIX behavior for files (overwrites)
            if (rename($tempDestination, $destination)) {
                return true;
            }

            // Fallback cleanup if rename fails
            if (file_exists($tempDestination)) {
                @unlink($tempDestination);
            }

            return false;
        } catch (Throwable $e) {
            // Ensure resources are released
            if (is_resource($ch)) {
                curl_close($ch);
            }
            if (is_resource($fp)) {
                fclose($fp);
            }
            if (file_exists($tempDestination)) {
                @unlink($tempDestination);
            }

            return false;
        }
    }

    public function send(): Response
    {
        if (! $this->requestUrl || ! $this->requestMethod) {
            $errorResponse = $this->_createErrorResponse('URL and method must be configured before calling send().');

            return new Response($errorResponse);
        }

        $rawResponse = $this->_executeWithRetry();

        $this->requestUrl = null;
        $this->requestMethod = null;
        $this->requestData = null;
        $this->retryCount = 0;
        $this->retryDelayMs = 0;
        $this->queryParams = [];

        return new Response($rawResponse);
    }

    public static function pool(callable $callback): Batch
    {
        $requests = $callback();

        if (! is_array($requests)) {
            $requests = [];
        }

        return new Batch($requests);
    }

    public static function concurrent(callable $callback): array
    {
        $requests = $callback();

        if (! is_array($requests) || empty($requests)) {
            return [new Response(self::_createErrorResponse('Concurrent callback did not return any requests.'))];
        }

        $multiHandle = curl_multi_init();
        $handlesMap = [];

        foreach ($requests as $key => $curlInstance) {
            if (! $curlInstance instanceof self || ! $curlInstance->requestUrl) {
                continue;
            }

            $ch = curl_init();
            $urlWithQuery = $curlInstance->_buildUrlWithQuery();
            $curlInstance->requestUrl = $urlWithQuery;

            self::_configureHandle($ch, $curlInstance);
            curl_multi_add_handle($multiHandle, $ch);

            $handlesMap[(int) $ch] = $key;

            $curlInstance->requestUrl = $curlInstance->_removeQueryParameters($urlWithQuery);
        }

        $responseObjects = self::_runMultiExecutor($multiHandle, $handlesMap);

        curl_multi_close($multiHandle);

        return $responseObjects;
    }

    public function async(): Promise
    {
        if (! $this->requestUrl || ! $this->requestMethod) {
            throw new RuntimeException('URL and method must be configured before calling async().');
        }

        $multiHandle = curl_multi_init();
        $ch = curl_init();

        $urlWithQuery = $this->_buildUrlWithQuery();
        // Clone to avoid side effects if reused
        $tempInstance = clone $this;
        $tempInstance->requestUrl = $urlWithQuery;

        self::_configureHandle($ch, $tempInstance);
        curl_multi_add_handle($multiHandle, $ch);

        // Restore original URL on this instance (though clone protects most, being safe)
        // Actually we cloned, so $this is safe.

        return new Promise($multiHandle, $ch, $this);
    }

    public static function _executeBatch(Batch $batch): void
    {
        $requests = $batch->getRequests();
        $multiHandle = curl_multi_init();
        $handlesMap = [];

        foreach ($requests as $key => $curlInstance) {
            $ch = curl_init();

            $urlWithQuery = $curlInstance->_buildUrlWithQuery();
            $curlInstance->requestUrl = $urlWithQuery;

            self::_configureHandle($ch, $curlInstance);
            curl_multi_add_handle($multiHandle, $ch);

            $handlesMap[(int) $ch] = $key;

            $curlInstance->requestUrl = $curlInstance->_removeQueryParameters($urlWithQuery);
        }

        $results = self::_runMultiExecutor($multiHandle, $handlesMap);
        curl_multi_close($multiHandle);

        foreach ($results as $key => $response) {
            $batch->storeResult($key, $response);
        }
    }

    private function _executeWithRetry(): array
    {
        $maxAttempts = $this->retryCount + 1;
        $attempt = 0;
        $lastResponse = $this->_createErrorResponse('Request failed with no response.');

        do {
            $attempt++;
            $url = $this->_buildUrlWithQuery();

            $lastResponse = $this->_executeSingleAttempt($url, $this->requestMethod, $this->requestData);

            if ($lastResponse['status'] === true && $lastResponse['http_code'] >= 200 && $lastResponse['http_code'] < 400) {
                return $lastResponse;
            }

            if ($attempt < $maxAttempts && $this->retryDelayMs > 0) {
                usleep($this->retryDelayMs * 1000);
            }
        } while ($attempt < $maxAttempts);

        return $lastResponse;
    }

    private function _executeSingleAttempt(string $url, string $method, mixed $data = null): array
    {
        $ch = curl_init();

        $tempInstance = clone $this;
        $tempInstance->requestUrl = $url;
        $tempInstance->requestMethod = $method;
        $tempInstance->requestData = $data;

        self::_configureHandle($ch, $tempInstance);

        $result = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        if ($curlError || $result === false) {
            curl_close($ch);

            return self::_createErrorResponse("cURL Error ({$curlErrno}): " . $curlError);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close($ch);

        $header = substr($result, 0, $headerSize);
        $body = substr($result, $headerSize);
        $parsedHeaders = $this->_parseResponseHeaders($header);

        return [
            'status' => true,
            'message' => 'Success',
            'http_code' => $httpCode,
            'body' => $body,
            'headers' => $parsedHeaders,
        ];
    }

    private function _buildUrlWithQuery(): string
    {
        $url = $this->requestUrl;
        if (empty($this->queryParams) || $url === null) {
            return $url ?? '';
        }

        $queryString = http_build_query($this->queryParams);

        if (str_contains($url, '?')) {
            return $url . '&' . $queryString;
        }

        return $url . '?' . $queryString;
    }

    private function _removeQueryParameters(string $url): string
    {
        $parts = explode('?', $url, 2);

        return $parts[0];
    }

    private static function _configureHandle(CurlHandle $ch, self $instance): void
    {
        $method = strtolower($instance->requestMethod);

        curl_setopt($ch, CURLOPT_URL, $instance->requestUrl);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $instance->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $instance->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $instance->verifySsl ? 2 : 0);

        if ($instance->authType === 'token' && $instance->authToken) {
            $instance->headers = $instance->headers->set('Authorization', $instance->authToken);
        } elseif ($instance->authType === 'basic' || $instance->authType === 'digest') {
            curl_setopt($ch, CURLOPT_USERPWD, "{$instance->authUsername}:{$instance->authPassword}");
            if ($instance->authType === 'basic') {
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            } elseif ($instance->authType === 'digest') {
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            }
        }

        $preparedHeaders = self::_prepareHeaders($instance->headers->all());
        curl_setopt($ch, CURLOPT_HTTPHEADER, $preparedHeaders);

        if ($method !== 'get') {
            if ($method === 'post') {
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            }

            if (! empty($instance->requestData) || ! empty($instance->fileAttachments)) {
                $postFields = $instance->_preparePostData($instance->requestData);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            }
        }
    }

    private static function _runMultiExecutor(CurlMultiHandle $multiHandle, array $handlesMap): array
    {
        $running = null;
        $tempCurl = new self();
        $responseObjects = [];

        do {
            $mrc = curl_multi_exec($multiHandle, $running);

            if ($running > 0) {
                curl_multi_select($multiHandle, 0.5);
            }

            while ($done = curl_multi_info_read($multiHandle)) {
                $ch = $done['handle'];
                $key = $handlesMap[(int) $ch];

                $rawResponse = self::_getSingleResult($ch);
                $responseObjects[$key] = new Response($rawResponse);

                curl_multi_remove_handle($multiHandle, $ch);
                curl_close($ch);
            }
        } while ($running > 0 && $mrc === CURLM_OK);

        return $responseObjects;
    }

    private static function _getSingleResult(CurlHandle $ch): array
    {
        $error = curl_error($ch);

        if ($error) {
            return self::_createErrorResponse('cURL Error #: ' . $error);
        }

        $result = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $header = substr($result, 0, $headerSize);
        $body = substr($result, $headerSize);
        $parser = new self();
        $parsedHeaders = $parser->_parseResponseHeaders($header);

        return [
            'status' => true,
            'message' => 'Success',
            'http_code' => $httpCode,
            'body' => $body,
            'headers' => $parsedHeaders,
        ];
    }

    private function _preparePostData(mixed $data): mixed
    {
        $contentType = strtolower($this->headers->get('content-type') ?? '');

        if (! empty($this->fileAttachments) || str_contains($contentType, 'multipart/form-data')) {
            $dataArray = is_array($data) ? $data : [];

            foreach ($this->fileAttachments as $name => $fileInfo) {
                $dataArray[$name] = new CURLFile(
                    $fileInfo['path'],
                    $fileInfo['mime'] ?? '',
                    $fileInfo['name'] ?? basename($fileInfo['path'])
                );
            }

            return $dataArray;
        }

        if (str_contains($contentType, 'application/json')) {
            return json_encode($data);
        }

        if (str_contains($contentType, 'application/xml') || str_contains($contentType, 'text/xml')) {
            return Format::asXml(is_array($data) ? $data : []);
        }

        if (is_string($data) && (in_array($this->requestMethod, ['put', 'patch', 'delete']) || $contentType === 'text/plain')) {
            return $data;
        }

        $dataArray = is_array($data) ? $data : [];

        return $dataArray ? http_build_query($dataArray) : '';
    }

    private static function _createErrorResponse(string $message): array
    {
        return [
            'status' => false,
            'message' => $message,
            'http_code' => 0,
            'body' => null,
            'headers' => null,
        ];
    }

    private static function _prepareHeaders(array $data): array
    {
        $headers = [];
        foreach ($data as $key => $value) {
            if (strtolower($key) === 'content-type' && str_contains(strtolower((string) $value), 'multipart/form-data')) {
                continue;
            }
            $headers[] = $key . ': ' . $value;
        }

        return $headers;
    }

    private function _parseResponseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $lines = array_filter(explode("\r\n", $rawHeaders));

        foreach ($lines as $line) {
            if (preg_match('/^HTTP\//', $line)) {
                $headers['Status-Line'] = $line;

                continue;
            }

            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);

                if (isset($headers[$key])) {
                    if (! is_array($headers[$key])) {
                        $headers[$key] = [$headers[$key]];
                    }
                    $headers[$key][] = $value;
                } else {
                    $headers[$key] = $value;
                }
            }
        }

        return $headers;
    }
}
