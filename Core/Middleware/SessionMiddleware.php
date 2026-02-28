<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class SessionMiddleware implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Middleware;

use Closure;
use Core\Services\ConfigServiceInterface;
use Helpers\Http\Request;
use Helpers\Http\Response;
use Helpers\Http\Session;

class SessionMiddleware implements MiddlewareInterface
{
    private readonly Session $session;

    private readonly ConfigServiceInterface $config;

    public function __construct(Session $session, ConfigServiceInterface $config)
    {
        $this->session = $session;
        $this->config = $config;
    }

    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        $this->session->start();

        if ($this->config->get('session.regenerate')) {
            $this->session->periodicRegenerate();
        }

        return $next($request, $response);
    }
}
