<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * BlacklistFirewall performs checks on incoming requests and blocks access if the request meets
 * certain conditions defined in a blacklist.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Drivers;

class BlacklistFirewall extends BaseFirewall
{
    public function handle(): void
    {
        $blacklist = $this->getConfig('blacklist');

        if (! $blacklist['enable']) {
            return;
        }

        $agent = $this->agent;
        $block = $this->agentIsBlocked($blacklist, $agent);

        if ($block && $this->routeExists($blacklist['routes'], $this->request->route())) {

            $this->auditTrail('Blacklisted');
            $is_api = $this->request->routeIsApi();
            $statusCode = 403;

            if ($is_api) {
                $response = $this->getJsonResponsePayload(['message' => 'Forbidden'], $statusCode);
            } else {
                $response = $this->getViewResponsePayload('403.html', $statusCode);
            }

            $this->setResponse($response);
            $this->is_blocked = true;

            return;
        }
    }

    private function agentIsBlocked(array $blacklist, object $agent): bool
    {
        $blockConfig = $blacklist['block'];
        $ip = $agent->ip();

        if ($this->isBlockedIp($blockConfig['ips'], $ip)) {
            return true;
        }

        if (in_array($agent->platform(), $blockConfig['platforms'], true)) {
            return true;
        }

        if (in_array($agent->browser(), $blockConfig['browsers'], true)) {
            return true;
        }

        if (in_array($agent->device(), $blockConfig['devices'], true)) {
            return true;
        }

        return false;
    }

    private function isBlockedIp(array $ips, string $ip): bool
    {
        if (in_array($ip, $ips['specific'], true)) {
            return true;
        }

        return $this->dynamicIpCheck($ips['dynamic'], $ip);
    }

    private function dynamicIpCheck(array $dynamic_ips, string $ip): bool
    {
        foreach ($dynamic_ips as $block) {
            if (strpos($ip, $block) === 0) {
                return true;
            }
        }

        return false;
    }
}
