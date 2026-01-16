<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Exception thrown for fatal errors.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Error;

use Exception;
use Throwable;

class FatalErrorException extends Exception
{
    private array $errorDetails;

    public function __construct(string $message, array $errorDetails, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorDetails = $errorDetails;
    }

    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }
}
