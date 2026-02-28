<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Services\ConfigServiceInterface;
use Helpers\Http\Request;
use Helpers\Http\Response;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $config
    ) {
    }

    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        // Resource Isolation Policy (Fetch Metadata)
        // Blocks malicious cross-site requests (CSRF, XSSI) while allowing legitimate navigation.
        if ($this->config->get('security_headers.fetch_metadata.enabled', false)) {
            $fetchSite = $request->header('sec-fetch-site');
            $fetchMode = $request->header('sec-fetch-mode');

            // If the header is present and indicates a cross-site request...
            if ($fetchSite && ! in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) {
                $isNavigation = ($fetchMode === 'navigate');
                $isSafeMethod = in_array($request->method(), ['GET', 'HEAD'], true);

                if (! ($isNavigation && $isSafeMethod)) {
                    return $response->status(403)->body('Forbidden by Resource Isolation Policy');
                }
            }
        }

        $response = $next($request, $response);

        if (! $this->config->get('security_headers.enabled', true)) {
            return $response;
        }

        $headers = [];

        $headers['X-Frame-Options'] = $this->config->get('security_headers.x_frame_options', 'SAMEORIGIN');
        $headers['X-Content-Type-Options'] = $this->config->get('security_headers.x_content_type_options', 'nosniff');
        $headers['X-XSS-Protection'] = $this->config->get('security_headers.x_xss_protection', '1; mode=block');
        $headers['Referrer-Policy'] = $this->config->get('security_headers.referrer_policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy: Control browser features
        $permissionsPolicy = $this->config->get('security_headers.permissions_policy', [
            'geolocation' => '()',
            'microphone' => '()',
            'camera' => '()',
        ]);

        if (! empty($permissionsPolicy)) {
            $headers['Permissions-Policy'] = implode(', ', array_map(
                fn ($feature, $allowlist) => "$feature=$allowlist",
                array_keys($permissionsPolicy),
                array_values($permissionsPolicy)
            ));
        }

        // Strict-Transport-Security: Enforce HTTPS (only on HTTPS connections)
        if ($request->isSecure() && $this->config->get('security_headers.hsts_enabled', true)) {
            $maxAge = $this->config->get('security_headers.hsts_max_age', 31536000); // 1 year
            $includeSubDomains = $this->config->get('security_headers.hsts_include_subdomains', true);
            $preload = $this->config->get('security_headers.hsts_preload', false);

            $hstsValue = "max-age=$maxAge";
            if ($includeSubDomains) {
                $hstsValue .= '; includeSubDomains';
            }
            if ($preload) {
                $hstsValue .= '; preload';
            }

            $headers['Strict-Transport-Security'] = $hstsValue;
        }

        // Content-Security-Policy (optional, can be very restrictive)
        $csp = $this->config->get('security_headers.content_security_policy');
        if ($csp) {
            $headers['Content-Security-Policy'] = $csp;
        }

        return $response->header($headers);
    }
}
