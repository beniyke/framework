<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Manages batch HTTP requests.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http\Client;

use Closure;

class Batch
{
    private array $requests = [];

    private array $results = [];

    private bool $stopOnFailure = false;

    private bool $hasFailed = false;

    private ?Closure $beforeCallback = null;

    private ?Closure $progressCallback = null;

    private ?Closure $thenCallback = null;

    private ?Closure $catchCallback = null;

    private ?Closure $finallyCallback = null;

    public function __construct(array $requests)
    {
        $this->requests = $requests;
    }

    public function before(Closure $callback): self
    {
        $this->beforeCallback = $callback;

        return $this;
    }

    public function progress(Closure $callback): self
    {
        $this->progressCallback = $callback;

        return $this;
    }

    public function then(Closure $callback): self
    {
        $this->thenCallback = $callback;

        return $this;
    }

    public function catch(Closure $callback): self
    {
        $this->catchCallback = $callback;
        $this->stopOnFailure = true;

        return $this;
    }

    public function finally(Closure $callback): self
    {
        $this->finallyCallback = $callback;

        return $this;
    }

    public function send(): Batch
    {
        Curl::_executeBatch($this);

        return $this;
    }

    public function getRequests(): array
    {
        return $this->requests;
    }

    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function storeResult(string|int $key, Response $response): void
    {
        $this->results[$key] = $response;
    }

    public function getProgressCallback(): ?Closure
    {
        return $this->progressCallback;
    }

    public function getCatchCallback(): ?Closure
    {
        return $this->catchCallback;
    }

    public function getBeforeCallback(): ?Closure
    {
        return $this->beforeCallback;
    }

    public function getThenCallback(): ?Closure
    {
        return $this->thenCallback;
    }

    public function getFinallyCallback(): ?Closure
    {
        return $this->finallyCallback;
    }

    public function hasFailed(): bool
    {
        return $this->hasFailed;
    }

    public function setFailed(bool $status): void
    {
        $this->hasFailed = $status;
    }

    public function shouldStopOnFailure(): bool
    {
        return $this->stopOnFailure;
    }
}
