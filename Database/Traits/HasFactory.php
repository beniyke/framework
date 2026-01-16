<?php

declare(strict_types=1);

namespace Database\Traits;

use Testing\Factories\Factory;

trait HasFactory
{
    /**
     * Create a new factory instance for the model.
     */
    public static function factory(?int $count = null): Factory
    {
        $factory = Factory::factoryForModel(static::class);

        if ($count !== null) {
            $factory->count($count);
        }

        return $factory;
    }
}
