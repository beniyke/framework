<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Debugger Package Setup Manifest
 * This file defines what gets registered when the Debugger package is installed.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

return [
    'providers' => [],
    'middleware' => [
        'web' => [
            Debugger\Middleware\DebuggerMiddleware::class,
        ]
    ],
];
