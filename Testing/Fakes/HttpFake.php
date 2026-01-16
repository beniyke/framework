<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fake HTTP client for testing outgoing HTTP requests.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use PHPUnit\Framework\Assert as PHPUnit;

class HttpFake
{
    /**
     * Stubbed responses by URL pattern.
     *
     * @var array<string, array{status: int, body: mixed, headers: array}>
     */
    protected array $stubs = [];

    /**
     * Default response when no stub matches.
     *
     * @var array{status: int, body: mixed, headers: array}
     */
    protected array $defaultResponse = [
        'status' => 200,
        'body' => '',
        'headers' => [],
    ];

    /**
     * All recorded requests.
     *
     * @var array<int, array{method: string, url: string, data: mixed, headers: array}>
     */
    protected array $requests = [];

    /**
     * Request headers for next request.
     *
     * @var array<string, string>
     */
    protected array $pendingHeaders = [];

    /**
     * Define stubbed responses.
     *
     * @param array<string, mixed> $stubs URL pattern => response or callable
     */
    public function fake(array $stubs = []): self
    {
        foreach ($stubs as $pattern => $response) {
            if (is_callable($response)) {
                $this->stubs[$pattern] = $response;
            } elseif (is_array($response)) {
                $this->stubs[$pattern] = array_merge($this->defaultResponse, $response);
            } else {
                $this->stubs[$pattern] = [
                    'status' => 200,
                    'body' => $response,
                    'headers' => [],
                ];
            }
        }

        return $this;
    }

    /**
     * Set headers for the next request.
     *
     * @param array<string, string> $headers
     */
    public function headers(array $headers): self
    {
        $this->pendingHeaders = array_merge($this->pendingHeaders, $headers);

        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->pendingHeaders[$name] = $value;

        return $this;
    }

    /**
     * Perform a GET request.
     *
     * @return array{success: bool, status: int, body: mixed, headers: array}
     */
    public function get(string $url): array
    {
        return $this->request('GET', $url);
    }

    /**
     * Perform a POST request.
     *
     * @return array{success: bool, status: int, body: mixed, headers: array}
     */
    public function post(string $url, mixed $data = null): array
    {
        return $this->request('POST', $url, $data);
    }

    /**
     * Perform a PUT request.
     *
     * @return array{success: bool, status: int, body: mixed, headers: array}
     */
    public function put(string $url, mixed $data = null): array
    {
        return $this->request('PUT', $url, $data);
    }

    /**
     * Perform a PATCH request.
     *
     * @return array{success: bool, status: int, body: mixed, headers: array}
     */
    public function patch(string $url, mixed $data = null): array
    {
        return $this->request('PATCH', $url, $data);
    }

    /**
     * Perform a DELETE request.
     *
     * @return array{success: bool, status: int, body: mixed, headers: array}
     */
    public function delete(string $url, mixed $data = null): array
    {
        return $this->request('DELETE', $url, $data);
    }

    /**
     * Perform a request.
     *
     * @return array{success: bool, status: int, body: mixed, headers: array}
     */
    protected function request(string $method, string $url, mixed $data = null): array
    {
        // Record the request
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'data' => $data,
            'headers' => $this->pendingHeaders,
        ];

        $this->pendingHeaders = [];

        // Find matching stub
        $response = $this->findStub($url);

        return [
            'success' => $response['status'] >= 200 && $response['status'] < 300,
            'status' => $response['status'],
            'body' => $response['body'],
            'headers' => $response['headers'],
        ];
    }

    /**
     * Find a matching stub for the URL.
     *
     * @return array{status: int, body: mixed, headers: array}
     */
    protected function findStub(string $url): array
    {
        foreach ($this->stubs as $pattern => $response) {
            // Support wildcards
            $regex = str_replace(['*', '/'], ['.*', '\/'], $pattern);

            if (preg_match("/^{$regex}$/", $url)) {
                if (is_callable($response)) {
                    return $response($url);
                }

                return $response;
            }
        }

        return $this->defaultResponse;
    }

    /**
     * Assert that a request was sent.
     */
    public function assertSent(string $url, ?callable $callback = null): void
    {
        $matching = array_filter($this->requests, function ($request) use ($url, $callback) {
            if (! $this->urlMatches($request['url'], $url)) {
                return false;
            }

            return $callback ? $callback($request) : true;
        });

        PHPUnit::assertTrue(
            count($matching) > 0,
            "No request was sent to [{$url}]."
        );
    }

    /**
     * Assert that a request was sent with specific method.
     */
    public function assertSentWithMethod(string $method, string $url, ?callable $callback = null): void
    {
        $matching = array_filter($this->requests, function ($request) use ($method, $url, $callback) {
            if ($request['method'] !== strtoupper($method)) {
                return false;
            }

            if (! $this->urlMatches($request['url'], $url)) {
                return false;
            }

            return $callback ? $callback($request) : true;
        });

        PHPUnit::assertTrue(
            count($matching) > 0,
            "No {$method} request was sent to [{$url}]."
        );
    }

    /**
     * Assert that a request was not sent.
     */
    public function assertNotSent(string $url): void
    {
        $matching = array_filter($this->requests, function ($request) use ($url) {
            return $this->urlMatches($request['url'], $url);
        });

        PHPUnit::assertCount(
            0,
            $matching,
            "A request was unexpectedly sent to [{$url}]."
        );
    }

    /**
     * Assert total request count.
     */
    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount(
            $count,
            $this->requests,
            "Expected {$count} requests, but " . count($this->requests) . ' were sent.'
        );
    }

    /**
     * Assert no requests were sent.
     */
    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty(
            $this->requests,
            'Requests were sent unexpectedly.'
        );
    }

    protected function urlMatches(string $actualUrl, string $pattern): bool
    {
        if ($actualUrl === $pattern) {
            return true;
        }

        // Support wildcards
        $regex = str_replace(['*', '/'], ['.*', '\/'], $pattern);

        return (bool) preg_match("/^{$regex}$/", $actualUrl);
    }

    /**
     * Get all recorded requests.
     *
     * @return array<int, array{method: string, url: string, data: mixed, headers: array}>
     */
    public function recorded(): array
    {
        return $this->requests;
    }

    /**
     * Clear recorded requests and stubs.
     */
    public function clear(): void
    {
        $this->requests = [];
        $this->stubs = [];
    }
}
