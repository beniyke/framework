<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Exception thrown when validation fails.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
{
    /**
     * @var array
     */
    private array $errors;

    /**
     * Create a new validation exception instance.
     *
     * @param string $message
     * @param array  $errors
     */
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
