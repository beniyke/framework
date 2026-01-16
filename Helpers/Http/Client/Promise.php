<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Represents an asynchronous HTTP request promise.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http\Client;

use CurlHandle;
use CurlMultiHandle;

class Promise
{
    private CurlMultiHandle $multiHandle;

    private CurlHandle $easyHandle;

    private Curl $curlInstance;

    private bool $completed = false;

    private int $resultCode = 0;

    public function __construct(CurlMultiHandle $multiHandle, CurlHandle $easyHandle, Curl $curlInstance)
    {
        $this->multiHandle = $multiHandle;
        $this->easyHandle = $easyHandle;
        $this->curlInstance = $curlInstance;

        // Kickstart the request
        $this->process();
    }

    public function wait(): Response
    {
        if ($this->completed) {
            return $this->getResponse();
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($this->multiHandle, $active);
            if ($active) {
                // Wait for activity on any curl-connection
                if (curl_multi_select($this->multiHandle) === -1) {
                    usleep(100);
                }
            }
        } while ($active && $mrc === CURLM_OK);

        // Check for results
        while ($done = curl_multi_info_read($this->multiHandle)) {
            if ($done['handle'] === $this->easyHandle) {
                $this->resultCode = $done['result'];
            }
        }

        $this->completed = true;

        return $this->getResponse();
    }

    private function getResponse(): Response
    {
        $error = curl_error($this->easyHandle);
        $errno = curl_errno($this->easyHandle);

        // If curl_errno is 0 but we have a result code from multi_info_read, use that
        if ($errno === 0 && $this->resultCode !== 0) {
            $errno = $this->resultCode;
            $error = curl_strerror($errno);
        }

        if ($error || $errno) {
            $this->cleanup();

            return new Response([
                'status' => false,
                'message' => "Async request failed: {$error} ({$errno})",
                'http_code' => 0,
                'errno' => $errno,
                'body' => null,
                'headers' => [],
            ]);
        }

        $content = curl_multi_getcontent($this->easyHandle);
        $info = curl_getinfo($this->easyHandle);

        $this->cleanup();

        $headerSize = $info['header_size'];
        $header = substr($content, 0, $headerSize);
        $body = substr($content, $headerSize);

        // Use the Curl instance to parse headers (even though it's private, we might need a public helper or reflection?
        // Actually, let's duplicate the simple parsing logic here or expose it.
        // For robustness, let's keep it self-contained or use a public static helper if available.
        // Curl::_parseResponseHeaders is private. I will implement a similar parser here to avoid access issues.
        $headers = $this->parseHeaders($header);

        return new Response([
            'status' => true,
            'message' => 'Success',
            'http_code' => $info['http_code'],
            'body' => $body,
            'headers' => $headers,
        ]);
    }

    private function cleanup(): void
    {
        // Remove handle and close multi handle
        // Check if resources is valid before operating
        if (isset($this->multiHandle) && is_resource($this->multiHandle) || $this->multiHandle instanceof CurlMultiHandle) {
            curl_multi_remove_handle($this->multiHandle, $this->easyHandle);
            curl_multi_close($this->multiHandle);
        }

        // Easy handle is usually closed when multi handle removes it? No, we should close it.
        if (isset($this->easyHandle) && (is_resource($this->easyHandle) || $this->easyHandle instanceof CurlHandle)) {
            curl_close($this->easyHandle);
        }
    }

    private function process(): void
    {
        $active = null;
        curl_multi_exec($this->multiHandle, $active);
    }

    private function parseHeaders(string $rawHeaders): array
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
