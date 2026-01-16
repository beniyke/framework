<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * DebuggerMiddleware injects the DebugBar into HTML responses when debugging is enabled.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Middleware;

use Closure;
use Core\Middleware\MiddlewareInterface;
use Core\Services\ConfigServiceInterface;
use Debugger\DebuggerInterface;
use Helpers\Http\Request;
use Helpers\Http\Response;

class DebuggerMiddleware implements MiddlewareInterface
{
    private ConfigServiceInterface $config;

    private DebuggerInterface $debugger;

    public function __construct(ConfigServiceInterface $config, DebuggerInterface $debugger)
    {
        $this->config = $config;
        $this->debugger = $debugger;
    }

    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        $response = $next($request, $response);

        if (! $this->config->isDebugEnabled()) {
            return $response;
        }

        if ($this->isHtmlResponse($response) && $this->isInjectable($response)) {
            $response = $this->injectDebugbar($response);
        }

        return $response;
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->getHeader('Content-Type');

        return is_string($contentType) && str_contains(strtolower($contentType), 'text/html');
    }

    private function isInjectable(Response $response): bool
    {
        $code = $response->getStatusCode();

        if ($response->isSuccessful($code) && $code !== 204) {
            return ! $response->isRedirect($code);
        }

        return false;
    }

    private function injectDebugbar(Response $response): Response
    {
        $html = $response->getContent();

        if (! is_string($html) || $html === '') {
            return $response;
        }

        $head_code = $this->debugger->renderer()->renderHead();
        $body_code = $this->debugger->renderer()->render();

        if (! empty($head_code)) {
            $html = preg_replace(
                '/(<\/head>)/i',
                $head_code . "\n" . '$1',
                $html,
                1,
                $count
            );
        }

        $body_injected = false;
        if (! empty($body_code)) {
            $html = preg_replace(
                '/(<\/body>)/i',
                $body_code . "\n" . '$1',
                $html,
                1,
                $count
            );
            $body_injected = ($count > 0);
        }

        if (! empty($body_code) && ! $body_injected) {
            $html .= $body_code;
        }

        $response->body($html);

        if ($response->getHeader('Content-Length') === null) {
            $response->header(['Content-Length' => strlen($html)]);
        }

        return $response;
    }
}
