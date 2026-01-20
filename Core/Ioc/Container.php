<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Dependency Injection Container.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Ioc;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

class Container implements ContainerInterface
{
    private static ?self $instance = null;

    private array $bindings = [];

    private array $cache = [];

    private array $buildStack = [];

    private array $tags = [];

    private array $contextual = [];

    private array $deferred = [];

    private array $reflectionCache = [];

    private array $dependencyCache = [];

    private array $registeredProviders = [];

    private array $taggedCache = [];

    private bool $enablePropertyInjection = true;

    private array $conventionRules = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $concrete = $concrete ?? $abstract;
        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->cache[$abstract] = $instance;
    }

    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function forgetCachedInstance(string $abstract): void
    {
        unset($this->cache[$abstract]);
    }

    public function get(string $id)
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        if (isset($this->deferred[$id])) {
            $this->registerProvider($this->deferred[$id]);
        }

        // Check for contextual binding first
        $contextualBinding = $this->getBindingFromContextualStack($id);
        if ($contextualBinding !== null) {
            $binding = $contextualBinding;
        } elseif (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
        } else {
            $binding = $this->bindFromConvention($id) ? $this->bindings[$id] : null;
        }

        if ($binding === null) {
            return $this->build($id);
        }

        $instance = $binding['concrete'] instanceof Closure ? $binding['concrete']($this) : $this->build($binding['concrete']);

        if ($binding['shared']) {
            $this->cache[$id] = $instance;
        }

        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->cache[$id]) || isset($this->bindings[$id]) || isset($this->deferred[$id]) || class_exists($id);
    }

    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

    public function tag(array $services, string $tag): void
    {
        if (! isset($this->tags[$tag])) {
            $this->tags[$tag] = [];
        }
        $this->tags[$tag] = array_merge($this->tags[$tag], $services);
    }

    public function tagged(string $tag): array
    {
        if (isset($this->taggedCache[$tag])) {
            return $this->taggedCache[$tag];
        }

        $results = [];
        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $service) {
                $results[] = $this->get($service);
            }
        }

        $this->taggedCache[$tag] = $results;

        return $results;
    }

    public function call(array $callback, array $parameters = []): mixed
    {
        $method = new ReflectionMethod($callback[0], $callback[1]);
        $dependencies = $this->resolveDependenciesForMethod($method, $parameters);

        return $method->invokeArgs($callback[0], $dependencies);
    }

    public function disablePropertyInjection(): void
    {
        $this->enablePropertyInjection = false;
    }

    protected function getBindingFromContextualStack(string $id): ?array
    {
        if (! isset($this->contextual[$id])) {
            return null;
        }

        foreach (array_reverse($this->buildStack) as $concrete) {
            foreach ($this->contextual[$id] as $binding) {
                if ($binding['concrete'] === $concrete) {
                    return ['concrete' => $binding['implementation'], 'shared' => false];
                }
            }
        }

        return null;
    }

    protected function getReflectionClass(string $class): ReflectionClass
    {
        if (! isset($this->reflectionCache[$class])) {
            $this->reflectionCache[$class] = new ReflectionClass($class);
        }

        return $this->reflectionCache[$class];
    }

    protected function build(string $concrete): object
    {
        if (in_array($concrete, $this->buildStack)) {
            throw new Exception("Circular dependency detected: {$concrete} is in the build stack.");
        }

        $this->buildStack[] = $concrete;

        try {
            $reflector = $this->getReflectionClass($concrete);

            if (! $reflector->isInstantiable()) {
                throw new Exception("Class {$concrete} is not instantiable.");
            }

            $constructor = $reflector->getConstructor();

            if (is_null($constructor)) {
                $instance = new $concrete();
            } else {
                $dependencies = $this->resolveDependenciesForMethod($constructor);
                $instance = $reflector->newInstanceArgs($dependencies);
            }

            if ($this->enablePropertyInjection) {
                $this->injectProperties($instance);
            }

            return $instance;
        } finally {
            array_pop($this->buildStack);
        }
    }

    protected function injectProperties(object $instance): void
    {
        $reflector = $this->getReflectionClass(get_class($instance));
        $properties = $reflector->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $type = $property->getType();

            if ($type && ! $type instanceof ReflectionUnionType && $type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $className = $type->getName();

                if ($this->has($className)) {
                    $dependency = $this->get($className);
                    $property->setValue($instance, $dependency);
                }
            }
        }
    }

    private function getTypeName(?ReflectionType $type = null): ?string
    {
        if (! $type) {
            return null;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(fn ($t) => $t->getName(), $type->getTypes()));
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return (string) $type;
    }

    protected function resolveDependenciesForMethod(ReflectionMethod $method, array $givenParameters = []): array
    {
        $cacheKey = $method->getDeclaringClass()->getName() . '::' . $method->getName();
        if (isset($this->dependencyCache[$cacheKey])) {
            return $this->resolveCachedDependencies($this->dependencyCache[$cacheKey], $givenParameters);
        }

        $dependencies = [];
        $parameters = $method->getParameters();
        $givenParametersCount = count($givenParameters);
        $isAssociative = ! array_is_list($givenParameters);
        $indexedParameterIndex = 0;
        $dependencyMetadata = [];

        foreach ($parameters as $parameter) {
            if ($isAssociative && isset($givenParameters[$parameter->getName()])) {
                $dependencies[] = $givenParameters[$parameter->getName()];
                $dependencyMetadata[] = ['type' => 'given', 'name' => $parameter->getName()];

                continue;
            }

            $type = $parameter->getType();
            $isClassType = $type instanceof ReflectionNamedType && ! $type->isBuiltin();

            if (! $isAssociative && ! $isClassType && $indexedParameterIndex < $givenParametersCount) {
                $value = $givenParameters[$indexedParameterIndex];
                $dependencies[] = $this->castToType($value, $type, $parameter);

                $dependencyMetadata[] = ['type' => 'cast', 'value' => $value, 'typeName' => $this->getTypeName($type)];

                $indexedParameterIndex++;

                continue;
            }

            $resolved = $this->resolveParameter($parameter);
            $dependencies[] = $resolved;

            if ($isClassType) {
                $dependencyMetadata[] = [
                    'type' => 'resolved',
                    'name' => $parameter->getName(),
                    'typeName' => $this->getTypeName($type),
                ];
            } else {
                $dependencyMetadata[] = [
                    'type' => 'value',
                    'value' => $resolved,
                    'typeName' => $this->getTypeName($type),
                ];
            }
        }

        $this->dependencyCache[$cacheKey] = $dependencyMetadata;

        return $dependencies;
    }

    protected function resolveCachedDependencies(array $dependencyMetadata, array $givenParameters): array
    {
        $dependencies = [];
        foreach ($dependencyMetadata as $meta) {
            if ($meta['type'] === 'given') {
                $dependencies[] = $givenParameters[$meta['name']];
            } elseif ($meta['type'] === 'value') {
                $dependencies[] = $meta['value'];
            } elseif ($meta['type'] === 'cast') {
                // For 'cast' types, we don't try to re-instantiate ReflectionNamedType
                // We just use the value as is or implement a string-based cast if needed.
                // Given the current implementation, returning the value is safest.
                $dependencies[] = $meta['value'];
            } else {
                $serviceId = $meta['typeName'] ?? null;

                if ($serviceId === null || str_contains($serviceId, '|')) {
                    throw new Exception(
                        'Unresolvable complex or untyped dependency detected!
                        Stack Context: ' . implode(' -> ', array_reverse($this->buildStack)) . "
                        Problematic Dependency: '{$meta['name']}' with type '{$serviceId}'"
                    );
                }

                $dependencies[] = $this->get($serviceId);
            }
        }

        return $dependencies;
    }

    protected function castToType($value, ?ReflectionType $type, ?ReflectionParameter $parameter)
    {
        if ($value === null && ($type === null || $type->allowsNull())) {
            return null;
        }

        if (! $type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        switch ($typeName) {
            case 'string':
                return (string) $value;
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return (bool) $value;
            case 'array':
                return (array) $value;
            default:
                return $value;
        }
    }

    protected function resolveParameter(ReflectionParameter $parameter)
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                if ($this->has($parameter->getName())) {
                    return $this->get($parameter->getName());
                }

                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }
                throw new Exception("Unresolvable dependency: parameter '{$parameter->getName()}' of built-in type '{$type->getName()}' has no default value and no named binding.");
            }

            return $this->get($type->getName());
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $subType) {
                if ($subType instanceof ReflectionNamedType && ! $subType->isBuiltin()) {
                    try {
                        return $this->get($subType->getName());
                    } catch (Exception $e) {
                    }
                }
            }
        }

        if ($this->has($parameter->getName())) {
            return $this->get($parameter->getName());
        }

        if (! $type) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new Exception("Unresolvable dependency: untyped parameter '{$parameter->getName()}' has no default value and no named binding.");
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new Exception("Unresolvable dependency: complex type hint for parameter '{$parameter->getName()}' with type '{$this->getTypeName($type)}'");
    }

    public function addContextualBinding(string $concrete, string $abstract, $implementation): void
    {
        $this->contextual[$abstract][] = ['concrete' => $concrete, 'implementation' => $implementation];
    }

    public function setConventionRules(array $rules): void
    {
        $this->conventionRules = $rules;
    }

    protected function bindFromConvention(string $abstract): bool
    {
        foreach ($this->conventionRules as $rule) {
            $interfaceNamespace = $rule['interface_namespace'] ?? '';
            $implementationNamespace = $rule['implementation_namespace'] ?? '';
            $interfaceSuffix = $rule['interface_suffix'] ?? '';

            if (str_starts_with($abstract, $interfaceNamespace) && str_ends_with($abstract, $interfaceSuffix)) {
                $baseName = str_replace([$interfaceNamespace, $interfaceSuffix], '', $abstract);
                $concrete = $implementationNamespace . $baseName;

                if (class_exists($concrete)) {
                    $this->bind($abstract, $concrete);

                    return true;
                }
            }
        }

        return false;
    }

    public function registerDeferredProvider(string $providerClass, array $provides): void
    {
        foreach ($provides as $service) {
            $this->deferred[$service] = $providerClass;
        }
    }

    public function registerProvider(string $providerClass): void
    {
        if (isset($this->registeredProviders[$providerClass])) {
            return;
        }

        $provider = new $providerClass($this);
        $provider->register();
        $this->registeredProviders[$providerClass] = true;

        foreach (array_keys($this->deferred, $providerClass) as $service) {
            unset($this->deferred[$service]);
        }
    }

    public function make(string $abstract, array $parameters = []): object
    {
        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;
        $reflector = $this->getReflectionClass($concrete);
        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependenciesForMethod($constructor, $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    public static function getResolvedInstance(): ContainerInterface
    {
        return self::getInstance()->get(ContainerInterface::class);
    }
}
