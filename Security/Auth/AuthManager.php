<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Main authentication manager for the system.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth;

use Closure;
use Core\Services\ConfigServiceInterface;
use InvalidArgumentException;
use Security\Auth\Guards\SessionGuard;
use Security\Auth\Guards\TokenGuard;
use Security\Auth\Interfaces\AuthManagerInterface;
use Security\Auth\Interfaces\GuardInterface;
use Security\Auth\Interfaces\UserSourceInterface;
use Security\Auth\Sources\DatabaseUserSource;

class AuthManager implements AuthManagerInterface
{
    protected array $guards = [];

    protected array $customCreators = [];

    public function __construct(
        protected readonly ConfigServiceInterface $config
    ) {
    }

    public function guard(?string $name = null): GuardInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->guards[$name] ??= $this->resolve($name);
    }

    protected function resolve(string $name): GuardInterface
    {
        $config = $this->guardConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($name, $config);
        }

        return match ($config['driver']) {
            'session' => $this->createSessionDriver($name, $config),
            'token' => $this->createTokenDriver($name, $config),
            default => $this->createDynamicDriver($name, $config),
        };
    }

    protected function createDynamicDriver(string $name, array $config): GuardInterface
    {
        $method = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($name, $config);
        }

        throw new InvalidArgumentException("Auth driver [{$config['driver']}] for guard [{$name}] is not defined.");
    }

    protected function callCustomCreator(string $name, array $config): GuardInterface
    {
        return $this->customCreators[$config['driver']]($name, $config);
    }

    public function createSessionDriver(string $name, array $config): GuardInterface
    {
        $source = $this->resolveSource($config['source'] ?? $config['provider'] ?? null);

        return resolve(SessionGuard::class, [
            'name' => $name,
            'source' => $source,
        ]);
    }

    public function createTokenDriver(string $name, array $config): GuardInterface
    {
        $source = $this->resolveSource($config['source'] ?? $config['provider'] ?? null);

        return resolve(TokenGuard::class, [
            'name' => $name,
            'source' => $source,
        ]);
    }

    protected function guardConfig(string $name): ?array
    {
        return $this->config->get("auth.guards.{$name}");
    }

    public function getDefaultDriver(): string
    {
        return $this->config->get('auth.defaults.guard', 'web');
    }

    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    protected function resolveSource(?string $name = null): UserSourceInterface
    {
        $config = $this->config->get("auth.sources.{$name}");

        if (is_null($config)) {
            throw new InvalidArgumentException("Auth user source [{$name}] is not defined.");
        }

        return match ($config['driver'] ?? 'database') {
            'database' => $this->createDatabaseSource($config),
            default => throw new InvalidArgumentException("Auth user source driver [{$config['driver']}] is not defined."),
        };
    }

    protected function createDatabaseSource(array $config): UserSourceInterface
    {
        if (! isset($config['model'])) {
            throw new InvalidArgumentException("Auth user source [database] requires a 'model' configuration.");
        }

        return resolve(DatabaseUserSource::class, ['model' => $config['model']]);
    }

    public function logoutAll(): void
    {
        $guards = $this->config->get('auth.guards', []);

        foreach (array_keys($guards) as $name) {
            $this->guard($name)->logout();
        }
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->guard()->$method(...$parameters);
    }
}
