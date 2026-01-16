<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * AuthFirewall protects authentication endpoints from abuse.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Drivers;

use RuntimeException;

class AuthFirewall extends BaseFirewall
{
    private ?string $action = null;

    protected $callback;

    public function callback(callable $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    public function clear(): self
    {
        $this->action = 'clear';

        return $this;
    }

    public function fail(): self
    {
        $this->action = 'fail';

        return $this;
    }

    public function capture(): void
    {
        $key = $this->getKey();

        switch ($this->action) {
            case 'clear':
                $this->throttler->clear($key);
                break;
            case 'fail':
                $this->throttler->attempt($key);
                break;
        }
    }

    public function handle(): void
    {
        $auth = $this->getConfig('auth');

        if (! $auth['enable']) {
            $this->is_blocked = false;

            return;
        }

        if (! in_array($this->request->route(), $auth['routes'])) {
            $this->is_blocked = false;

            return;
        }

        if (! $this->request->isPost()) {
            $this->is_blocked = false;

            return;
        }

        $key = $this->getKey();
        $result = $this->throttler->check($key);

        $this->is_blocked = $result['is_blocked'];

        if ($this->is_blocked) {
            $time_remaining = $result['time_remaining'];

            $expression = $this->formatDuration($time_remaining);
            $error = str_replace('{duration}', $expression, $auth['response']);

            if (is_callable($this->callback)) {
                call_user_func($this->callback, $error);
            }

            $this->flash->error($error);

            if ($this->flash->hasSuccess()) {
                $this->flash->clearSuccess();
            }

            $this->auditTrail('Authentication trial limit maxed out.', $this->getIdentifier());
            $this->setResponse($this->getRedirectResponsePayload('login'));
        }
    }

    private function retrieveIdentifierValue(): string
    {
        $value = $this->getConfig('auth')['identity'];
        $identifierValue = $this->request->post($value);

        if (empty($identifierValue)) {
            throw new RuntimeException("Authentication identifier '{$value}' not found in POST data.");
        }

        return $identifierValue;
    }

    private function getKey(): string
    {
        $agent = $this->agent;
        $identifierValue = $this->retrieveIdentifierValue();

        return md5($agent->ip() . '-' . $agent->device() . '-' . $agent->platform() . '-' . $agent->browser() . '-' . $agent->version() . '-' . $identifierValue);
    }

    private function getIdentifier(): array
    {
        $value = $this->getConfig('auth')['identity'];

        return [$value => $this->retrieveIdentifierValue()];
    }

    private function formatDuration(int $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours . ' ' . inflect('hour', $hours);
        }

        if ($minutes > 0) {
            $parts[] = $minutes . ' ' . inflect('minute', $minutes);
        }

        if ($seconds > 0 || empty($parts)) {
            $parts[] = $seconds . ' ' . inflect('second', $seconds);
        }

        if (count($parts) > 1) {
            $last = array_pop($parts);

            return implode(', ', $parts) . ' and ' . $last;
        }

        return implode(' ', $parts);
    }
}
