<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Result Class: Encapsulates the outcome of an operation (e.g., Zipper::save()).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Zipper;

class Result
{
    private bool $success;

    private string $message;

    private array $data;

    private function __construct(bool $success, string $message, array $data = [])
    {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
    }

    public static function success(string $message, array $data = []): self
    {
        return new self(true, $message, $data);
    }

    public static function error(string $message, array $data = []): self
    {
        return new self(false, $message, $data);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->data;
        }

        return $this->data[$key] ?? null;
    }
}
