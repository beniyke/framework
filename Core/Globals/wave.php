<?php

declare(strict_types=1);

use Wave\Services\WaveManagerService;

if (! function_exists('wave')) {
    function wave(): WaveManagerService
    {
        return resolve(WaveManagerService::class);
    }
}
