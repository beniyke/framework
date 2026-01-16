<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Handles request throttling for the firewall.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Throttling;

use Core\Services\ConfigServiceInterface;
use Helpers\DateTimeHelper;
use Helpers\Http\Request;
use Security\Firewall\Persistence\CounterStore;

class Throttler
{
    private CounterStore $store;

    private array $config;

    public function __construct(CounterStore $store, ConfigServiceInterface $configService, Request $request)
    {
        $this->store = $store;

        $firewallKey = $request->routeIsApi() ? 'api-request' : 'auth';
        $fullFirewallConfig = $configService->get('firewall');
        $this->config = $fullFirewallConfig[$firewallKey];
    }

    public function check(string $key): array
    {
        $throttle = $this->config['throttle'];
        $data = $this->store->get($key);

        if (is_null($data)) {
            return [
                'is_blocked' => false,
                'time_remaining' => 0,
            ];
        }

        $requestCount = $data['request'];
        $startTime = DateTimeHelper::parse($data['datetime']);
        $elapsed = (int) $startTime->diffInSeconds(DateTimeHelper::now());

        if ($requestCount > $throttle['attempt']) {
            if ($elapsed < $throttle['delay']) {
                $timeRemaining = $throttle['delay'] - $elapsed;

                return [
                    'is_blocked' => true,
                    'time_remaining' => $timeRemaining,
                ];
            }
        }

        return [
            'is_blocked' => false,
            'time_remaining' => 0,
        ];
    }

    public function attempt(string $key): array
    {
        $throttle = $this->config['throttle'];
        $data = $this->store->atomicUpdate($key, $throttle['duration']);
        $requestCount = $data['request'];
        $startTime = DateTimeHelper::parse($data['datetime']);
        $elapsed = (int) $startTime->diffInSeconds(DateTimeHelper::now());

        if ($requestCount > $throttle['attempt']) {
            if ($elapsed < $throttle['delay']) {
                $timeRemaining = $throttle['delay'] - $elapsed;

                return [
                    'is_blocked' => true,
                    'time_remaining' => $timeRemaining,
                ];
            }
        }

        return [
            'is_blocked' => false,
            'time_remaining' => 0,
        ];
    }

    public function clear(string $key): void
    {
        $this->store->clear($key);
    }
}
