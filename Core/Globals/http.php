<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * HTTP helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Helpers\Http\Client\Curl;
use Helpers\Http\Flash;
use Helpers\Http\Request;
use Helpers\Http\Response;
use Helpers\Http\Session;
use Helpers\Http\UserAgent;

if (! function_exists('request')) {
    function request(): Request
    {
        return resolve(Request::class);
    }
}

if (! function_exists('response')) {
    function response(string $data = '', int $status = 200, array $headers = []): Response
    {
        return new Response($data, $status, $headers);
    }
}

if (! function_exists('redirect')) {
    function redirect(string $url = '', array $params = [], bool $internal = true): Response
    {
        if ($internal) {
            $url = url($url, $params);
        }

        return (new Response())->redirect($url);
    }
}

if (! function_exists('url')) {
    function url(?string $path = null, array $query = []): string
    {
        if ($query) {
            $append = http_build_query($query);
            $path .= (strpos($path, '?') !== false ? '&' : '?') . $append;
        }

        return request()->baseurl($path);
    }
}

if (! function_exists('route')) {
    function route(?string $uri = null, bool $re_route = false): string
    {
        return request()->route($uri, $re_route);
    }
}

if (! function_exists('current_url')) {
    function current_url(): string
    {
        return url(ltrim(request()->uri(), '/'));
    }
}

if (! function_exists('request_uri')) {
    function request_uri(): string
    {
        return ltrim(request()->uri(), '/');
    }
}

if (! function_exists('agent')) {
    function agent(): UserAgent
    {
        return new UserAgent($_SERVER);
    }
}

if (! function_exists('curl')) {
    function curl(): Curl
    {
        return new Curl();
    }
}

if (! function_exists('session')) {
    function session(?string $key = null, mixed $value = null): mixed
    {
        $session = resolve(Session::class);

        if (func_num_args() === 0) {
            return $session;
        }

        if (func_num_args() === 1) {
            return $session->get($key);
        }

        return $session->set($key, $value);
    }
}

if (! function_exists('flash')) {
    function flash(?string $type = null, mixed $message = null): mixed
    {
        $flash = resolve(Flash::class);

        if (func_num_args() === 0) {
            return $flash;
        }

        if (func_num_args() === 1) {
            return $flash->get($type);
        }

        return $flash->set($type, $message);
    }
}
