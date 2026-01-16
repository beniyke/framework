<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * HTTP Service Provider for registering HTTP-related services.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Providers;

use Core\Ioc\ContainerInterface;
use Core\Services\ConfigServiceInterface;
use Core\Services\ServiceProvider;
use Core\Support\Adapters\Interfaces\SapiInterface;
use Helpers\Http\Cookie;
use Helpers\Http\Flash;
use Helpers\Http\Request;
use Helpers\Http\Response;
use Helpers\Http\Session;
use Helpers\Http\UserAgent;

class HttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Session::class);

        $config = $this->container->get(ConfigServiceInterface::class);
        $session = $this->container->get(Session::class);

        $is_long_lived = $session->get('session.long_lived', false);
        $config_defaults = $config->get('session.cookie');

        $cookie_lifetime = $is_long_lived
            ? $config_defaults['remember_me_lifetime']
            : $config_defaults['lifetime'];

        Cookie::configureSessionCookie(
            $cookie_lifetime,
            $config_defaults['path'],
            $config_defaults['domain'],
            $config_defaults['secure'],
            $config_defaults['http_only'],
            $config_defaults['samesite']
        );

        $session->start();

        $this->container->singleton(Flash::class, function ($container) use ($session) {
            return new Flash($session);
        });

        $this->container->singleton(UserAgent::class, function (ContainerInterface $container) {
            return new UserAgent($_SERVER);
        });

        $this->container->singleton(Request::class, function (ContainerInterface $container) use ($config, $session) {
            return Request::createFromGlobals(
                $config,
                $container->get(SapiInterface::class),
                $session,
                $container->get(UserAgent::class)
            );
        });

        $this->container->instance(Response::class, new Response());
    }
}
