<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * BaseTask provides a template for tasks that can be processed in a queue,
 * ensuring consistency in task execution, messaging, and error handling.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue;

use Helpers\Data;
use Queue\Interfaces\Queueable;
use Throwable;

abstract class BaseTask implements Queueable
{
    protected mixed $payload;
    private const OCCURRENCE_ONCE = 'once';
    private const OCCURRENCE_ALWAYS = 'always';

    public function __construct(?Data $payload = null)
    {
        $this->payload = $payload;
    }

    protected static function once(): string
    {
        return self::OCCURRENCE_ONCE;
    }

    protected static function always(): string
    {
        return self::OCCURRENCE_ALWAYS;
    }

    abstract protected function successMessage(): string;

    abstract protected function failedMessage(): string;

    /**
     * Define the operations to be performed by the task.
     * This method should return true on success and false on failure.
     */
    abstract protected function execute(): bool;

    /**
     * Run the task and return a formatted response.
     */
    public function run(): object
    {
        try {
            if ($this->execute()) {
                return $this->createResponse('success', $this->successMessage());
            } else {
                return $this->createResponse('failed', $this->failedMessage());
            }
        } catch (Throwable $e) {
            return $this->createResponse('failed', $e->getMessage());
        }
    }

    /**
     * Create a standardized response for task results.
     */
    protected function createResponse(string $status, string $message): object
    {
        return (object) [
            'status' => $status,
            'message' => $message,
        ];
    }
}
