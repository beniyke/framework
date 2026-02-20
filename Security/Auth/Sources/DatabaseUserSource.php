<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * User source implementation for database-backed authentication.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Auth\Sources;

use InvalidArgumentException;
use Security\Auth\Contracts\Authenticatable;
use Security\Auth\Interfaces\UserSourceInterface;

class DatabaseUserSource implements UserSourceInterface
{
    public function __construct(protected string $model)
    {
        if (! class_exists($this->model)) {
            throw new InvalidArgumentException("User model [{$this->model}] does not exist.");
        }
    }

    public function retrieveById(int|string $id): ?Authenticatable
    {
        return $this->model::find($id);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials)) {
            return null;
        }

        $query = $this->model::query();

        foreach ($credentials as $key => $value) {
            if (str_contains($key, 'password')) {
                continue;
            }

            $query->where($key, $value);
        }

        return $query->first();
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        if (is_null($password)) {
            return false;
        }

        return password_verify($password, $user->getAuthPassword());
    }
}
