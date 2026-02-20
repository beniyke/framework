<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * MaintenanceFirewall is responsible for guarding against access to the application
 * during maintenance mode.
 *
 * If maintenance mode is enabled, it checks if the incoming request should be
 * allowed based on certain conditions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Drivers;

class MaintenanceFirewall extends BaseFirewall
{
    public function handle(): void
    {
        $maintenance = $this->getConfig('maintenance');
        $resource = $this->request->getRouteContext('resource');
        $is_locked_resource = ! empty($maintenance['locked_resources']) && in_array($resource, $maintenance['locked_resources'], true);

        // Block if global maintenance is enabled OR specific resource is locked
        if (! $maintenance['enable'] && ! $is_locked_resource) {
            return;
        }

        $agent = $this->agent;
        $block_conditions = $this->checkBlockConditions($maintenance, $agent);
        $agent_allowed = empty($block_conditions);
        $route_allowed = $this->routeExists($maintenance['allow']['routes'], $this->request->route());

        // Final allow condition:
        // Must be on an allowed route AND satisfy agent conditions
        $allow = $route_allowed && $agent_allowed;

        if (! $allow) {
            $message = $is_locked_resource ? "The {$resource} module is temporarily under maintenance." : 'Under maintenance.';
            $this->auditTrail("Access denied. {$message}");

            $is_api = $this->request->routeIsApi();
            $statusCode = 503;

            if ($is_api) {
                $response = $this->getJsonResponsePayload(['message' => "{$message} Service Unavailable"], $statusCode);
            } else {
                $response = $this->getViewResponsePayload('maintenance.html', $statusCode);
            }

            $this->setResponse($response);
            $this->is_blocked = true;

            return;
        }
    }

    private function checkBlockConditions(array $maintenance, object $agent): array
    {
        $block_conditions = [];

        foreach (['ips', 'browsers', 'platforms', 'devices'] as $condition) {
            $allow_config = $maintenance['allow'][$condition];

            if (! $allow_config['ignore']) {
                $method_name = rtrim($condition, 's');
                $check_value = $agent->{$method_name}();

                if (! in_array($check_value, $allow_config['list'], true)) {
                    $block_conditions[] = $condition;
                }
            }
        }

        return $block_conditions;
    }
}
