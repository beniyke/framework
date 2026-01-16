<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Queue setup configuration.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

return [
    'providers' => [
        Queue\Providers\QueueServiceProvider::class,
    ],
    'middleware' => [],
];
