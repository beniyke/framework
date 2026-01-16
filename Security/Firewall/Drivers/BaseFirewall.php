<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * BaseFirewall provides basic functionality and configuration options for a firewall system,
 * with specific implementation details left to child classes that extend this base class.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Drivers;

use Core\Services\ConfigServiceInterface;
use Helpers\Data;
use Helpers\DateTimeHelper;
use Helpers\File\Contracts\CacheInterface;
use Helpers\File\Paths;
use Helpers\Http\Flash;
use Helpers\Http\Request;
use Helpers\Http\UserAgent;
use Notify\Notifier;
use Security\Firewall\Notifications\FirewallEmailNotification;
use Security\Firewall\Throttling\Throttler;

abstract class BaseFirewall
{
    protected const FIREWALL_PATH = 'firewall';
    protected const AUDIT_CACHE_DURATION_SECONDS = 172800;

    protected bool $is_blocked = false;

    protected ?array $response = null;

    protected ConfigServiceInterface $config;

    protected CacheInterface $cache;

    protected UserAgent $agent;

    protected Notifier $notifier;

    protected Request $request;

    protected Flash $flash;

    protected Throttler $throttler;

    public function __construct(ConfigServiceInterface $config, CacheInterface $cache, UserAgent $agent, Notifier $notifier, Request $request, Flash $flash, Throttler $throttler)
    {
        $this->config = $config;
        $this->cache = $cache;
        $this->agent = $agent;
        $this->notifier = $notifier;
        $this->request = $request;
        $this->flash = $flash;
        $this->throttler = $throttler;
    }

    protected function getConfig(?string $value = null): array
    {
        $config = $this->config->get('firewall');

        return $value === null ? $config : $config[$value];
    }

    protected function cache(): CacheInterface
    {
        return $this->cache->withPath(static::FIREWALL_PATH);
    }

    protected function auditTrail(string $message, ?array $identifier = null): void
    {
        $agent = $this->agent;
        $cache = $this->cache();

        $unique_id = md5(static::getFirewallName() . "-{$agent->ip()}-{$agent->browser()}-{$agent->version()}-{$agent->platform()}-{$agent->device()}");

        if ($cache->has($unique_id)) {
            return;
        }

        $now = DateTimeHelper::now();
        $cache->write($unique_id, $now->format('Y-m-d H:i:s'), self::AUDIT_CACHE_DURATION_SECONDS);

        $data = [];
        $data['message'] = $message;
        $data['firewall'] = static::getFirewallName();
        $data['identifier'] = $identifier;
        $data['timestamp'] = $now->format('Y-m-d H:i A');
        $data['source']['ip'] = $agent->ip();
        $data['source']['browser'] = $agent->browser() . ' ' . $agent->version();
        $data['source']['platform'] = $agent->platform();
        $data['source']['device'] = $agent->device();

        $notification = $this->getConfig('notification');

        defer(function () use ($data, $notification) {
            if ($notification['mail']['status']) {
                $payload['to'] = $notification['mail']['to'];
                $payload['data'] = $data;

                $this->notifier->channel('email')
                    ->with(FirewallEmailNotification::class, Data::make($payload))
                    ->send();
            }
        });
    }

    private static function getFirewallName(): string
    {
        $split = explode('\\', get_called_class());

        return end($split);
    }

    protected function setResponse(array $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }

    public function isBlocked(): bool
    {
        return $this->is_blocked;
    }

    protected function getViewResponsePayload(string $template, int $code = 200): array
    {
        $content = Paths::coreViewTemplatePath($template);

        return $this->getResponsePayload($content, $code, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    protected function getJsonResponsePayload(array $content, int $code): array
    {
        return $this->getResponsePayload(json_encode($content), $code, ['Content-Type' => 'application/json']);
    }

    protected function getRedirectResponsePayload(string $route): array
    {
        return $this->getResponsePayload($this->request->baseUrl($route), 307);
    }

    protected function getResponsePayload(string $content, int $code, array $header = []): array
    {
        return compact('content', 'code', 'header');
    }

    protected function routeExists(array $routes, string $current_route): bool
    {
        $unpacked_routes = [];

        foreach ($routes as $route) {
            $unpacked_routes = array_merge($unpacked_routes, $this->unpackCollection($route));
        }

        return in_array($current_route, $unpacked_routes, true) || $this->resolveWildcard($unpacked_routes, $current_route);
    }

    private function resolveWildcard(array $routes, string $current_route): bool
    {
        $split_current = $this->splitRoute($current_route);

        foreach ($routes as $route) {
            $split_route = $this->splitRoute($route);
            if ($split_route['first'] === $split_current['first'] && $split_route['second'] === '*') {
                return true;
            }
        }

        return false;
    }

    private function unpackCollection(string $route): array
    {
        $parts = preg_split('/\{/', $route, 2);

        if (empty($parts[1])) {
            return [$route];
        }

        preg_match_all('/(?:\s*)([^,}]+)(?:\s*)(?:,|})/', $parts[1], $matches);

        return array_map(fn ($match) => $parts[0] . trim($match), $matches[1]);
    }

    private function splitRoute(string $route): array
    {
        $split = explode('/', ltrim($route, '/'), 2);

        return ['first' => $split[0], 'second' => $split[1] ?? null];
    }

    abstract public function handle(): void;
}
