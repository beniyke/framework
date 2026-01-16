<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class provides a convenient way to manage and display flash messages.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http;

use Helpers\Array\ArrayCollection as Arr;

class Flash
{
    private const SESSION_KEY = 'flash';

    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function peek(string $key): mixed
    {
        $flashData = $this->session->get(self::SESSION_KEY, []);

        return Arr::value($flashData, $key);
    }

    public function get(string $key): mixed
    {
        $value = $this->peek($key);
        $this->delete($key);

        return $value;
    }

    public function set(string $type, mixed $message): void
    {
        $flashData = $this->session->get(self::SESSION_KEY, []);
        $flashData = Arr::set($flashData, $type, $message);
        $this->session->set(self::SESSION_KEY, $flashData);
    }

    public function has(string $key): bool
    {
        $flashData = $this->session->get(self::SESSION_KEY, []);

        return Arr::has($flashData, $key);
    }

    public function delete(string $key): void
    {
        $flashData = $this->session->get(self::SESSION_KEY, []);
        $flashData = Arr::forget($flashData, $key);
        $this->session->set(self::SESSION_KEY, $flashData);
    }

    public function withInput(array $formData, mixed $errors): void
    {
        $this->set('old_input', $formData);
        if (! empty($errors)) {
            $this->set('input_errors', $errors);
        }
    }

    public function old(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->get('old_input');
        }

        return $this->get('old_input.' . $key);
    }

    public function peekInputError(string $field): mixed
    {
        return $this->peek('input_errors.' . $field);
    }

    public function getInputError(string $field): mixed
    {
        return $this->get('input_errors.' . $field);
    }

    public function getInputErrors(): array
    {
        return $this->get('input_errors') ?? [];
    }

    public function hasInputError(string $field): bool
    {
        return $this->has('input_errors.' . $field);
    }

    public function hasInputErrors(): bool
    {
        $errors = $this->peek('input_errors');

        return $this->has('input_errors') && is_array($errors) && (count($errors) > 0);
    }

    public function success(mixed $message): void
    {
        $this->set('success', $message);
    }

    public function error(mixed $message): void
    {
        $this->set('error', $message);
    }

    public function info(mixed $message): void
    {
        $this->set('info', $message);
    }

    public function hasSuccess(): bool
    {
        return $this->has('success');
    }

    public function hasError(): bool
    {
        return $this->has('error');
    }

    public function hasInfo(): bool
    {
        return $this->has('info');
    }

    public function getSuccess(): mixed
    {
        return $this->get('success');
    }

    public function getError(): mixed
    {
        return $this->get('error');
    }

    public function getInfo(): mixed
    {
        return $this->get('info');
    }

    public function clearSuccess(): void
    {
        $this->delete('success');
    }

    public function clearError(): void
    {
        $this->delete('error');
        $this->delete('input_errors');
    }

    public function clearInfo(): void
    {
        $this->delete('info');
    }
}
