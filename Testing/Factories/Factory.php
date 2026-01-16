<?php

declare(strict_types=1);

namespace Testing\Factories;

use Database\BaseModel;
use Database\Collections\ModelCollection;

abstract class Factory
{
    /**
     * The model the factory is for.
     */
    protected string $model;

    /**
     * The number of models to create.
     */
    protected ?int $count = null;

    /**
     * Define the model's default state.
     */
    abstract public function definition(): array;

    /**
     * Set the number of models that should be generated.
     */
    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Create a new instance of the model.
     */
    public function make(array $attributes = []): BaseModel|ModelCollection
    {
        if ($this->count === null) {
            return $this->makeInstance($attributes);
        }

        $models = [];

        for ($i = 0; $i < $this->count; $i++) {
            $models[] = $this->makeInstance($attributes);
        }

        return new ModelCollection($models);
    }

    /**
     * Create a new instance of the model and persist it to the database.
     */
    public function create(array $attributes = []): BaseModel|ModelCollection
    {
        $models = $this->make($attributes);

        if ($models instanceof BaseModel) {
            $models->save();

            return $models;
        }

        foreach ($models as $model) {
            $model->save();
        }

        return $models;
    }

    /**
     * Create a new instance of the model.
     */
    protected function makeInstance(array $attributes = []): BaseModel
    {
        $model = $this->model;

        return new $model(array_merge($this->definition(), $attributes));
    }

    /**
     * Create a new factory instance for the given model.
     */
    public static function factoryForModel(string $model): static
    {
        $factory = static::resolveFactoryName($model);

        return new $factory();
    }

    /**
     * Resolve the factory name for the given model.
     */
    public static function resolveFactoryName(string $model): string
    {
        // Convention: Namespace\Model -> Database\Factories\ModelFactory
        $parts = explode('\\', $model);
        $name = end($parts);

        return "Database\\Factories\\{$name}Factory";
    }
}
