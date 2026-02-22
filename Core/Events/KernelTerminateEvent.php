<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class KernelTerminateEvent implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Events;

use Helpers\Http\Request;
use Helpers\Http\Response;

class KernelTerminateEvent
{
    public function __construct(
        public readonly Request $request,
        public readonly Response $response
    ) {
    }
}
