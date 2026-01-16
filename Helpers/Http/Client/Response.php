<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Represents an HTTP response.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http\Client;

class Response
{
    private array $response;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function httpCode(): int
    {
        return $this->response['http_code'] ?? 0;
    }

    public function body(): ?string
    {
        return $this->response['body'];
    }

    public function isEmpty(): bool
    {
        return empty($this->body());
    }

    public function json(): mixed
    {
        $body = $this->body();
        if ($body === null) {
            return null;
        }

        return json_decode($body, true);
    }

    public function bodyJson(string $key, mixed $default = null): mixed
    {
        $data = $this->json();

        if (! is_array($data) || empty($key)) {
            return $default;
        }

        $keys = explode('.', $key);
        $current = $data;

        foreach ($keys as $segment) {
            if (is_array($current) && isset($current[$segment])) {
                $current = $current[$segment];
            } else {
                return $default;
            }
        }

        return $current;
    }

    public function responseHeaders(): ?array
    {
        return $this->response['headers'] ?? null;
    }

    public function header(string $key): ?string
    {
        $headers = $this->responseHeaders();
        if ($headers === null) {
            return null;
        }

        $normalizedKey = strtolower($key);

        foreach ($headers as $headerKey => $value) {
            if (strtolower($headerKey) === $normalizedKey) {
                return $value;
            }
        }

        return null;
    }

    public function message(): string
    {
        return $this->response['message'] ?? 'Unknown error';
    }

    public function successful(): bool
    {
        $code = $this->httpCode();

        return $code >= 200 && $code < 300;
    }

    public function failed(): bool
    {
        $code = $this->httpCode();

        return $code >= 400 || $this->response['status'] === false || $this->isTransportError();
    }

    public function clientError(): bool
    {
        $code = $this->httpCode();

        return $code >= 400 && $code < 500;
    }

    public function serverError(): bool
    {
        $code = $this->httpCode();

        return $code >= 500 && $code < 600;
    }

    public function isRedirect(): bool
    {
        $code = $this->httpCode();

        return $code >= 300 && $code < 400;
    }

    public function ok(): bool
    {
        return $this->httpCode() === 200;
    }

    public function notFound(): bool
    {
        return $this->httpCode() === 404;
    }

    public function isTransportError(): bool
    {
        return ($this->response['errno'] ?? 0) !== 0;
    }

    public function transportCode(): int
    {
        return $this->response['errno'] ?? 0;
    }

    public function transferTime(): float
    {
        return $this->response['info']['total_time'] ?? 0.0;
    }

    public function location(): ?string
    {
        return $this->header('Location');
    }

    public function onError(callable $callback): self
    {
        if ($this->failed()) {
            $callback($this);
        }

        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->successful();
    }

    public function isFailed(): bool
    {
        return $this->failed();
    }

    public function isClientError(): bool
    {
        return $this->clientError();
    }

    public function isServerError(): bool
    {
        return $this->serverError();
    }

    public function isNotFound(): bool
    {
        return $this->notFound();
    }

    public function isOk(): bool
    {
        return $this->ok();
    }

    public function getHttpCode(): int
    {
        return $this->httpCode();
    }

    public function getErrorMessage(): string
    {
        return $this->message();
    }

    public function getBodyJson(string $key, mixed $default = null): mixed
    {
        return $this->bodyJson($key, $default);
    }
}
