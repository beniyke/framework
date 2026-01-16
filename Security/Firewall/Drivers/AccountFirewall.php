<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * AccountFirewall provides functionality to block users based on account status.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Drivers;

use InvalidArgumentException;
use RuntimeException;

class AccountFirewall extends BaseFirewall
{
    protected array $user;

    protected $callback;

    public function callback(callable $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    public function user(array $user): self
    {
        if (! isset($user['id'])) {
            throw new InvalidArgumentException("User array must contain an 'id' key.");
        }

        $this->user = $user;

        return $this;
    }

    public function handle(): void
    {
        $account = $this->getConfig('account');

        if (! $account['enable']) {
            $this->is_blocked = false;

            return;
        }

        if (! isset($this->user)) {
            throw new RuntimeException('AccountFirewall requires calling user() before handle().');
        }

        $this->guard();
    }

    public function guard(): void
    {
        $account = $this->getConfig('account');
        $key = $this->getRequestKey();

        $result = $this->throttler->attempt($key);

        $this->is_blocked = $result['is_blocked'];

        if ($this->is_blocked) {
            $time_remaining = $result['time_remaining'];
            $formatted_duration = $this->formatDuration($time_remaining);
            $error = str_replace('{duration}', $formatted_duration, $account['response']);

            if (is_callable($this->callback)) {
                call_user_func($this->callback, $error);
            }

            $this->flash->error($error);
            $this->auditTrail('Account rate limit exceeded.', $this->getIdentifier());
            $this->setResponse($this->getRedirectResponsePayload('login'));
        }
    }

    private function getRequestKey(): string
    {
        $agent = $this->agent;

        return md5($agent->ip() . '-' . $agent->device() . '-' . $agent->platform() . '-' . $agent->browser() . '-' . $agent->version() . '-' . $this->user['id']);
    }

    private function getIdentifier(): array
    {
        $identifier = $this->user;
        unset($identifier['id']);

        return $identifier;
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
