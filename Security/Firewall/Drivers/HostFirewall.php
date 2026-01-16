<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * HostFirewall provides protection against unauthorized access to the application
 * based on the requested host.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Drivers;

class HostFirewall extends BaseFirewall
{
    public function handle(): void
    {
        $host_config = $this->getConfig('host');

        if (! $host_config['enable']) {
            return;
        }

        $requested_host = rtrim($this->request->host(), '/');
        $block = ! in_array($requested_host, $host_config['allow'], true);

        if ($block) {
            $this->auditTrail('Application host changed');

            $is_api = $this->request->routeIsApi();
            $response = null;
            $statusCode = 403;

            if ($is_api) {
                $response = $this->getJsonResponsePayload(['message' => 'Resource Locked'], $statusCode);
            } else {
                $response = $this->getViewResponsePayload('host.html', $statusCode);
            }

            $this->setResponse($response);
            $this->is_blocked = true;

            return;
        }
    }
}
