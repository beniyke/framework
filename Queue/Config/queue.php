<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Queue configuration.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

return [
    'batch_size' => env('QUEUE_BATCH_SIZE', 10),
    'max_retry' => env('QUEUE_MAX_RETRY', 3),
    'delay_minutes' => env('QUEUE_DELAY', 5),
    'timeout_minutes' => env('QUEUE_TIMEOUT', 5),
    'check_state' => env('QUEUE_CHECK_STATE', true),
];
