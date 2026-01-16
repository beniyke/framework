<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for middleware.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Middleware;

use Closure;
use Helpers\Http\Request;
use Helpers\Http\Response;

interface MiddlewareInterface
{
    /**
     * Initialize the middleware.
     *
     * This method should contain the logic to set up or execute
     * the middleware functionality. It's called when the middleware
     * is invoked.
     */
    public function handle(Request $request, Response $response, Closure $next): mixed;
}
