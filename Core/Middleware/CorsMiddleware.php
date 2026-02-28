<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Services\ConfigServiceInterface;
use Helpers\Http\Request;
use Helpers\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $config
    ) {
    }

    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        if (! $this->config->get('cors.enabled', false)) {
            return $next($request, $response);
        }

        $origin = $request->header('Origin');
        $allowedOrigins = $this->config->get('cors.allowed_origins', ['*']);

        if ($origin && $this->isOriginAllowed($origin, $allowedOrigins)) {
            $allowOrigin = in_array('*', $allowedOrigins) ? '*' : $origin;

            if ($request->isOptions()) {
                return $response->header([
                    'Access-Control-Allow-Origin' => $allowOrigin,
                    'Access-Control-Allow-Methods' => implode(', ', $this->config->get('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'])),
                    'Access-Control-Allow-Headers' => implode(', ', $this->config->get('cors.allowed_headers', ['Content-Type', 'Authorization', 'X-Requested-With'])),
                    'Access-Control-Max-Age' => (string) $this->config->get('cors.max_age', 86400),
                    'Access-Control-Allow-Credentials' => $this->config->get('cors.allow_credentials', false) ? 'true' : 'false',
                ])->status(204);
            }

            $response = $next($request, $response);
            $response->header(['Access-Control-Allow-Origin' => $allowOrigin]);

            if ($this->config->get('cors.allow_credentials', false)) {
                $response->header(['Access-Control-Allow-Credentials' => 'true']);
            }

            $exposedHeaders = $this->config->get('cors.exposed_headers', []);
            if (! empty($exposedHeaders)) {
                $response->header(['Access-Control-Expose-Headers' => implode(', ', $exposedHeaders)]);
            }

            return $response;
        }

        return $next($request, $response);
    }

    private function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        if (in_array('*', $allowedOrigins)) {
            return true;
        }

        foreach ($allowedOrigins as $allowed) {
            if ($allowed === $origin) {
                return true;
            }

            // Wildcard subdomain match (e.g., *.example.com)
            if (str_starts_with($allowed, '*.')) {
                $domain = substr($allowed, 2);
                if (str_ends_with($origin, $domain)) {
                    return true;
                }
            }
        }

        return false;
    }
}
