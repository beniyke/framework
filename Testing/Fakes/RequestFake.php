<?php

declare(strict_types=1);

namespace Testing\Fakes;

use Core\Services\ConfigServiceInterface;
use Core\Support\Adapters\Interfaces\SapiInterface;
use Helpers\Http\Request;
use Helpers\Http\Session;
use Helpers\Http\UserAgent;

class RequestFake
{
    /**
     * Create a mocked Request object for testing.
     *
     * @param string $uri    The request URI.
     * @param string $method The HTTP method.
     * @param array  $data   The payload data (POST or GET).
     * @param array  $server Additional server settings (e.g., HTTP_ACCEPT).
     *
     * @return Request
     */
    public static function create(string $uri = '/', string $method = 'GET', array $data = [], array $server = []): Request
    {
        $method = strtoupper($method);

        $_POST = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']) ? $data : [];
        $_GET = ($method === 'GET') ? $data : [];

        unset($_SERVER['HTTP_X_REQUESTED_WITH'], $_SERVER['HTTP_ACCEPT']);

        $baseServer = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
            'PHP_SELF' => '/index.php' . $uri,
            'SCRIPT_NAME' => '/index.php',
        ];

        $_SERVER = array_merge($_SERVER, $baseServer, $server);

        return Request::createFromGlobals(
            resolve(ConfigServiceInterface::class),
            resolve(SapiInterface::class),
            resolve(Session::class),
            resolve(UserAgent::class)
        );
    }
}
