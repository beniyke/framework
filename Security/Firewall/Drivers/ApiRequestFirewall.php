<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * ApiRequestFirewall provides functionality for handling and guarding incoming Api requests
 * based on specified criteria, including atomic rate limiting.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Drivers;

class ApiRequestFirewall extends BaseFirewall
{
    public function handle(): void
    {
        $requestConfig = $this->getConfig('api-request');

        if (
            ! $requestConfig['enable'] ||
            ! in_array(strtolower($this->request->method()), $requestConfig['method'], true)
        ) {
            return;
        }

        if ($this->routeExists($requestConfig['routes'], $this->request->route())) {
            if (! $this->hasApiRequestIdentifier()) {
                $this->auditTrail('Unauthorized access (Missing Identifier).');
                $this->sendJsonResponse(401, 'Unauthorized access');

                return;
            }

            if ($requestConfig['scheme']['enable'] && ! in_array($this->request->scheme(), $requestConfig['scheme']['allow'], true)) {

                $this->auditTrail('Invalid request scheme.', $this->getIdentifier());
                $this->sendJsonResponse(
                    $requestConfig['scheme']['response']['code'],
                    $requestConfig['scheme']['response']['message']
                );

                return;
            }

            if ($requestConfig['content-type']['enable'] && in_array(strtolower($this->request->method()), $requestConfig['content-type']['method'], true)) {
                $contentType = $this->filterContentType($this->request->header('content-type'));

                if (! in_array($contentType, $requestConfig['content-type']['allow'], true)) {
                    $this->auditTrail('Invalid content type.', $this->getIdentifier());
                    $this->sendJsonResponse($requestConfig['content-type']['response']['code'], $requestConfig['content-type']['response']['message']);

                    return;
                }
            }

            $this->guard();
        }
    }

    public function guard(): void
    {
        $requestConfig = $this->getConfig('api-request');
        $key = $this->generateKey();
        $result = $this->throttler->attempt($key);

        $this->is_blocked = $result['is_blocked'];

        if ($this->is_blocked) {
            $this->auditTrail('API request limit exceeded.', $this->getIdentifier());

            $this->sendJsonResponse($requestConfig['response']['code'], $requestConfig['response']['message']);

            return;
        }
    }

    private function sendJsonResponse(int $code, string $message): void
    {
        $response = $this->getJsonResponsePayload(['message' => $message], $code);

        $this->is_blocked = true;
        $this->setResponse($response);
    }

    private function filterContentType(?string $value): string
    {
        return $value ? strtolower(explode(';', $value)[0]) : '';
    }

    private function retrieveApiRequestIdentifier(): ?string
    {
        $requestConfig = $this->getConfig('api-request');
        $method = array_key_first($requestConfig['identifier']);
        $value = $requestConfig['identifier'][$method];

        return $this->request->{$method}($value);
    }

    private function hasApiRequestIdentifier(): bool
    {
        return ! empty($this->retrieveApiRequestIdentifier());
    }

    private function generateKey(): string
    {
        $identifier = $this->retrieveApiRequestIdentifier();
        $ip = $this->agent->ip();

        return md5($identifier . '-' . $ip);
    }

    private function getIdentifier(): array
    {
        $requestConfig = $this->getConfig('api-request');
        $method = array_key_first($requestConfig['identifier']);
        $value = $requestConfig['identifier'][$method];

        return [$value => $this->retrieveApiRequestIdentifier()];
    }
}
