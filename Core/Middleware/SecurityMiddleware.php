<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Middleware for application-level security checks (CSRF, Honeypot, etc.).
 * Should be executed after SessionMiddleware.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Middleware;

use Closure;
use Helpers\Http\Flash;
use Helpers\Http\Request;
use Helpers\Http\Response;

class SecurityMiddleware implements MiddlewareInterface
{
    private readonly Flash $flash;

    public function __construct(Flash $flash)
    {
        $this->flash = $flash;
    }

    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        if ($request->getRouteContext('is_exclusive')) {
            return $next($request, $response);
        }

        if ($request->isStateChanging()) {
            if (! $request->isSecurityValid()) {
                return $this->handleSecurityFailure($request, $response);
            }
        }

        return $next($request, $response);
    }

    private function handleSecurityFailure(Request $request, Response $response): Response
    {
        $referer_route = $request->refererRoute();
        $fallback_route = '/';
        $target_route = $referer_route ?? $fallback_route;
        $target_url = $request->baseUrl($target_route);

        $this->flash->error('Security check failed. Please try again.');

        return $response->redirect($target_url);
    }
}
