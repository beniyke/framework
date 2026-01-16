<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for the Dependency Injection Container.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Ioc;

interface ContainerInterface
{
    /**
     * Binds an abstract to a concrete implementation.
     *
     * @param string     $abstract The abstract type, usually an interface or class name.
     * @param mixed|null $concrete The concrete implementation. Can be a class name, a Closure, or null.
     * @param bool       $shared   Whether the binding should be a singleton.
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void;

    /**
     * Binds an abstract as a singleton.
     *
     * @param string     $abstract The abstract type, usually an interface or class name.
     * @param mixed|null $concrete The concrete implementation. Can be a class name, a Closure, or null.
     */
    public function singleton(string $abstract, mixed $concrete = null): void;

    /**
     * Binds a concrete instance to an abstract.
     *
     * @param string $abstract The abstract type.
     * @param object $instance The concrete object instance.
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * Resolves and returns a service instance from the container.
     *
     * @param string $id The service ID, typically a class or interface name.
     *
     * @return mixed The resolved service instance.
     */
    public function get(string $id);

    /**
     * Checks if the container has a binding or instance for the given ID.
     *
     * @param string $id The service ID.
     *
     * @return bool True if a binding exists, false otherwise.
     */
    public function has(string $id): bool;

    /**
     * Binds a contextual implementation when a specific concrete class is being built.
     *
     * @param string $concrete The concrete class that is being built.
     */
    public function when(string $concrete): ContextualBindingBuilder;

    /**
     * Tags a list of services with a given tag.
     *
     * @param array  $services An array of service IDs to be tagged.
     * @param string $tag      The tag name.
     */
    public function tag(array $services, string $tag): void;

    /**
     * Resolves all services with a given tag.
     *
     * @param string $tag The tag name.
     *
     * @return array An array of resolved service instances.
     */
    public function tagged(string $tag): array;

    /**
     * Calls a class method and injects its dependencies.
     *
     * @param array $callback   An array containing the object and method name, e.g., [$object, 'method'].
     * @param array $parameters An array of additional parameters to pass to the method.
     *
     * @return mixed The return value of the called method.
     */
    public function call(array $callback, array $parameters = []): mixed;

    /**
     * Creates a new instance of a class, resolving its dependencies.
     *
     * @param string $abstract   The abstract type or concrete class name.
     * @param array  $parameters An array of parameters to override constructor dependencies.
     *
     * @return object The newly created instance.
     */
    public function make(string $abstract, array $parameters = []): object;

    /**
     * Registers a service provider.
     *
     * @param string $providerClass The class name of the provider.
     */
    public function registerProvider(string $providerClass): void;

    /**
     * Registers a deferred service provider.
     *
     * @param string $providerClass The class name of the provider.
     * @param array  $provides      The services provided by the provider.
     */
    public function registerDeferredProvider(string $providerClass, array $provides): void;
}
