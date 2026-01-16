<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Server extract and store information about the server and HTTP headers.
 *
 * It provides a way to extract useful information from an HTTP server request,
 * such as HTTP headers and authentication information, and provides default
 * values if any of the required parameters are missing.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http;

class Server extends Header
{
    public function __construct(array $server)
    {
        $server = array_replace([
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'HTTP_HOST' => 'localhost',
            'HTTP_USER_AGENT' => '',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '',
            'SCRIPT_FILENAME' => '',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time()
        ], $server);

        parent::__construct($server);
    }

    public function getHeaders(): array
    {
        $headers = [];
        $contentHeaders = ['CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true];

        foreach ($this->header as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[substr($key, 5)] = $value;
            } elseif (isset($contentHeaders[$key])) {
                $headers[$key] = $value;
            }
        }

        if (isset($this->header['PHP_AUTH_USER'])) {
            $headers['PHP_AUTH_USER'] = $this->header['PHP_AUTH_USER'];
            $headers['PHP_AUTH_PW'] = isset($this->header['PHP_AUTH_PW']) ? $this->header['PHP_AUTH_PW'] : '';
        } else {
            $authorizationHeader = null;
            if (isset($this->header['HTTP_AUTHORIZATION'])) {
                $authorizationHeader = $this->header['HTTP_AUTHORIZATION'];
            } elseif (isset($this->header['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authorizationHeader = $this->header['REDIRECT_HTTP_AUTHORIZATION'];
            }

            if ($authorizationHeader !== null) {
                if (stripos($authorizationHeader, 'basic ') === 0) {
                    $exploded = explode(':', base64_decode(substr($authorizationHeader, 6)), 2);

                    if (count($exploded) == 2) {
                        [$headers['PHP_AUTH_USER'], $headers['PHP_AUTH_PW']] = $exploded;
                    }
                } elseif (empty($this->header['PHP_AUTH_DIGEST']) && (stripos($authorizationHeader, 'digest ') === 0)) {
                    $headers['PHP_AUTH_DIGEST'] = $authorizationHeader;
                    $this->header['PHP_AUTH_DIGEST'] = $authorizationHeader;
                } elseif (stripos($authorizationHeader, 'bearer ') === 0) {
                    $headers['AUTHORIZATION'] = $authorizationHeader;
                }
            }
        }

        if (isset($headers['AUTHORIZATION'])) {
            return $headers;
        }

        if (isset($headers['PHP_AUTH_USER'])) {
            $headers['AUTHORIZATION'] = 'Basic ' . base64_encode($headers['PHP_AUTH_USER'] . ':' . $headers['PHP_AUTH_PW']);
        } elseif (isset($headers['PHP_AUTH_DIGEST'])) {
            $headers['AUTHORIZATION'] = $headers['PHP_AUTH_DIGEST'];
        }

        return $headers;
    }
}
