<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Exception thrown when validation fails.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
{
    private array $errors;

    public function __construct(string $message, array $errors)
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
