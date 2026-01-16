<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Request handles and processes HTTP requests.
 * It provides a flexible and extensible way to handle requests, including
 * PSR-7 compliance and application-level security checks.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http;

use Core\Route\Traits\RouteTrait;
use Core\Services\ConfigServiceInterface;
use Core\Support\Adapters\Interfaces\SapiInterface;
use Helpers\File\Paths;
use Helpers\Http\Psr7\Stream;
use Helpers\Http\Psr7\Uri;
use Helpers\Macroable;

class Request
{
    use RouteTrait;
    use Macroable;

    private const CSRF_TOKEN = 'csrf_token';
    private const HONEY_POT = 'top_yenoh';
    private const CALLBACK_ROUTE = 'callback_route';
    private const METHOD = '_method';

    public const METHOD_POST = 'POST';
    public const METHOD_GET = 'GET';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_OPTIONS = 'OPTIONS';

    public const CONTENT_TYPE_JSON = 'application/json';
    public const CONTENT_TYPE_XML_APP = 'application/xml';
    public const CONTENT_TYPE_XML_TEXT = 'text/xml';
    public const CONTENT_TYPE_MULTIPART = 'multipart/form-data';

    private Collection $request;

    private Collection $query;

    private Collection $file;

    private Collection $cookie;

    private Server $server;

    private Header $header;

    private string $stream;

    private bool $method_override = false;

    private ?string $defined_method = null;

    private bool $sanitize = true;

    private ?string $csrf_token = null;

    private readonly bool $isPhpServer;

    private ?string $callback_route = null;

    private ?string $honey_pot = null;

    private ConfigServiceInterface $config;

    private SapiInterface $sapi;

    private Session $session;

    private UserAgent $agent;

    private string $protocol_version = '1.1';

    private ?Uri $uri = null;

    private ?Stream $body = null;

    private array $psr7_headers = [];

    private mixed $authenticatedUser = null;

    private ?string $authenticatedToken = null;

    public function __construct(array $server_data, array $get_data, array $post_data, array $file_data, array $cookie_data, ConfigServiceInterface $config, SapiInterface $sapi, Session $session, $agent, ?string $stream = null)
    {
        $this->agent = $agent;
        $this->config = $config;
        $this->sapi = $sapi;
        $this->server = new Server($server_data);
        $this->header = new Header($this->server->getHeaders());
        $this->isPhpServer = $sapi->isPhpServer();
        $this->stream = $stream ?? self::getInputContent();
        $this->session = $session;

        $this->query = new Collection($get_data);
        $this->cookie = new Collection($cookie_data);

        $processed_files = array_map(static fn (array $value): FileHandler|array => self::handleMultipleFiles($value), $file_data);
        $this->file = new Collection($processed_files);

        $request_data = self::parseContent($post_data, $this->stream);

        if (empty($request_data) && ! empty($this->stream)) {
            $stream_data = $this->parseContentFromStream($this->stream);
            if (! empty($stream_data)) {
                $request_data = array_merge($request_data, $stream_data);
            }
        }

        if (array_key_exists(self::HONEY_POT, $request_data)) {
            $this->honey_pot = $request_data[self::HONEY_POT];
            unset($request_data[self::HONEY_POT]);
        }

        if (array_key_exists(self::CSRF_TOKEN, $request_data)) {
            $this->csrf_token = $request_data[self::CSRF_TOKEN];
            unset($request_data[self::CSRF_TOKEN]);
        }

        if (array_key_exists(self::METHOD, $request_data)) {
            $this->method_override = true;
            $this->defined_method = $request_data[self::METHOD];
            unset($request_data[self::METHOD]);
        }

        if (array_key_exists(self::CALLBACK_ROUTE, $request_data)) {
            $this->callback_route = $request_data[self::CALLBACK_ROUTE];
            unset($request_data[self::CALLBACK_ROUTE]);
        }

        $this->request = new Collection($request_data);
        $this->protocol_version = $this->server->get('SERVER_PROTOCOL') ? substr($this->server->get('SERVER_PROTOCOL'), 5) : '1.1';
        $this->uri = new Uri($this->server->get('REQUEST_SCHEME') . '://' . $this->server->get('HTTP_HOST') . $this->server->get('REQUEST_URI'));
        $this->body = new Stream($this->stream);
        $this->psr7_headers = $this->header->all();
    }

    /**
     * Create a new Request instance from PHP's superglobals.
     * This is the recommended way to create a Request object in production.
     */
    public static function createFromGlobals(ConfigServiceInterface $config, SapiInterface $sapi, Session $session, $agent, ?string $content = null): self
    {
        return new self($_SERVER, $_GET, $_POST, $_FILES, $_COOKIE, $config, $sapi, $session, $agent, $content);
    }

    private static function handleMultipleFiles(array $file): FileHandler|array
    {
        if (is_array($file['name'])) {
            $data = [];
            for ($i = 0; $i < count($file['name']); $i++) {
                $data[] = new FileHandler([
                    'name' => $file['name'][$i],
                    'type' => $file['type'][$i],
                    'tmp_name' => $file['tmp_name'][$i],
                    'error' => $file['error'][$i],
                    'size' => $file['size'][$i],
                ]);
            }

            return $data;
        }

        return new FileHandler($file);
    }

    public function sanitize(bool $status): self
    {
        $this->sanitize = $status;

        return $this;
    }

    private function sanitizeRequest(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map(fn ($value) => $this->sanitizeRequest($value), $data);
        }
        if (empty($data) || ! is_string($data)) {
            return $data;
        }
        $sanitized = filter_var($data, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        return strip_tags($sanitized);
    }

    public function stream(): string
    {
        return $this->stream;
    }

    public function ip(): string
    {
        return $this->agent->ip();
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    public function userAgent(): string
    {
        return $this->agent->agentString();
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        $post = $this->retrieve('request', $key, $default);

        return $this->sanitize ? $this->sanitizeRequest($post) : $post;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function has(string|array $key): bool
    {
        if (is_array($key)) {
            return ! array_diff($key, array_keys($this->all()));
        }

        return array_key_exists($key, $this->all());
    }

    public function filled(string|array $value): bool
    {
        $all = $this->all();
        if (is_array($value)) {
            return count(array_filter($value, fn (string $key) => ! empty($all[$key]))) === count($value);
        }

        return ! empty($all[$value]);
    }

    public function anyIsFilled(array $values): bool
    {
        $all = $this->all();
        foreach ($values as $value) {
            if (! empty($all[$value])) {
                return true;
            }
        }

        return false;
    }

    public function exclude(array $exclude): array
    {
        return array_diff_key($this->all(), array_flip($exclude));
    }

    public function file(?string $key = null): mixed
    {
        return $this->retrieve('file', $key);
    }

    public function hasFile(): bool
    {
        return !empty($this->file());
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        $get = $this->retrieve('query', $key, $default);

        return $this->sanitize ? $this->sanitizeRequest($get) : $get;
    }

    public function isPost(): bool
    {
        return $this->method() === self::METHOD_POST;
    }

    public function isGet(): bool
    {
        return $this->method() === self::METHOD_GET;
    }

    public function isPut(): bool
    {
        return $this->method() === self::METHOD_PUT;
    }

    public function isPatch(): bool
    {
        return $this->method() === self::METHOD_PATCH;
    }

    public function isDelete(): bool
    {
        return $this->method() === self::METHOD_DELETE;
    }

    public function isOptions(): bool
    {
        return $this->method() === self::METHOD_OPTIONS;
    }

    public function isStateChanging(): bool
    {
        $method = $this->method();

        return in_array($method, [
            self::METHOD_POST,
            self::METHOD_PUT,
            self::METHOD_PATCH,
            self::METHOD_DELETE,
        ]);
    }

    public function isSecurityValid(): bool
    {
        if ($this->checkCsrf() && ! $this->csrfTokenIsValid()) {
            return false;
        }

        if ($this->checkHoneypot() && $this->isBot()) {
            return false;
        }

        if ($this->agent->isRobot()) {
            return false;
        }

        return true;
    }

    public function referer(): ?string
    {
        return $this->header('referer');
    }

    public function isBot(): bool
    {
        return ! empty($this->honey_pot);
    }

    public function isAjax(): bool
    {
        return strtoupper($this->server('HTTP_X_REQUESTED_WITH') ?? '') === 'XMLHTTPREQUEST';
    }

    public function server(?string $key = null): mixed
    {
        return $this->retrieve('server', $key);
    }

    public function header(?string $key = null): mixed
    {
        $key = $key ? strtoupper(str_replace('-', '_', $key)) : null;

        return $this->retrieve('header', $key);
    }

    public function method(): string
    {
        $method = $this->server('REQUEST_METHOD');
        if ($this->method_override && in_array(strtolower($this->defined_method), ['put', 'patch', 'delete'])) {
            $method = $this->defined_method;
        }

        return strtoupper($this->isAjax() ? ($this->getAjaxMethod() ?? self::METHOD_GET) : ($method ?? self::METHOD_GET));
    }

    public function getAjaxMethod(): ?string
    {
        if ($this->isAjax()) {
            return ! empty($this->header('content-type')) ? self::METHOD_POST : self::METHOD_GET;
        }

        return null;
    }

    public function all(): array
    {
        $all = $this->get() + $this->post() + $this->file();

        return $this->sanitize ? $this->sanitizeRequest($all) : $all;
    }

    public function cookies(?string $key = null): mixed
    {
        return $this->retrieve('cookie', $key);
    }

    public function uri(): string
    {
        $uri = preg_replace('/\+/', '/', ($this->server('REQUEST_URI') ?? ''));

        return $this->stripCommonPrefix($uri, $this->path());
    }

    public function config(string $key): mixed
    {
        return $this->config->get($key);
    }

    public function host(): string
    {
        $host = $this->sapi->isCli() ? $this->config('host') : $this->server('HTTP_HOST');

        return rtrim($host, '/') . '/';
    }

    public function path(): string
    {
        $root = explode('/', $this->server('PHP_SELF'));
        array_pop($root);
        $root = implode('/', $root);

        return $this->isPhpServer ? '' : $root;
    }

    public function domain(): string
    {
        return $this->server('SERVER_NAME');
    }

    public function scheme(bool $suffix = false): string
    {
        $scheme = $this->sapi->isCli() ? 'http' . ($this->secure() ? 's' : '') : ($this->server('REQUEST_SCHEME') ?? 'http');

        return $suffix ? $scheme . '://' : $scheme;
    }

    public function secure(): bool
    {
        return ! empty($this->server('HTTPS')) && $this->server('HTTPS') !== 'off';
    }

    public function baseUrl(?string $url = null): string
    {
        return $this->scheme(true) . rtrim($this->host(), '/') . rtrim($this->path(), '/') . '/' . $url;
    }

    public function fullRouteByName(string $name): string
    {
        return $this->baseUrl($this->routeName($name));
    }

    public function fullRoute(?string $uri = null, bool $re_route = false, array $params = []): string
    {
        $routePath = $this->route($uri, $re_route);
        $path = $this->resolvePathVariables($routePath, $params);
        $fullUrl = $this->baseUrl($path);

        if (! empty($params)) {
            $fullUrl .= '?' . http_build_query($params);
        }

        return $fullUrl;
    }

    private function resolvePathVariables(string $path, array &$params): string
    {
        $path = preg_replace_callback('/\{(\w+)\}/', function ($matches) use (&$params) {
            $key = $matches[1];
            if (isset($params[$key])) {
                $value = $params[$key];
                unset($params[$key]);

                return $value;
            }

            return $matches[0];
        }, $path);

        return $path;
    }

    public function route(?string $uri = null, bool $re_route = false): string
    {
        $url = (explode('?', ltrim($this->uri(), '/'))[0] ?: null);
        $route = $this->config('route.default');

        if ($url) {
            $split = explode('/', $url);
            $class = array_shift($split);
            $route = $class;

            if (is_dir(Paths::appSourcePath(ucfirst($class)))) {
                $route .= '/' . array_shift($split);
            }
        }

        return $re_route ? $this->reRoute($route, $uri) : $route . ($uri ? '/' . $uri : '');
    }

    private function reRoute(string $route, ?string $uri): string
    {
        $parts = explode('/', $route);
        if (count($parts) > 2) {
            array_pop($parts);
        }
        array_pop($parts);
        if ($uri) {
            $parts[] = $uri;
        }

        return implode('/', $parts);
    }

    public function getCsrfToken(): string
    {
        $token = $this->session->get(self::CSRF_TOKEN);

        return $this->csrfTokenHasExpired($token) ? $this->generateCsrfToken() : $token;
    }

    private function generateCsrfToken(): string
    {
        $extra = $this->config('csrf.origin_check') ? sha1($this->server('REMOTE_ADDR') . $this->server('HTTP_USER_AGENT')) : '';
        $token = base64_encode(time() . $extra . bin2hex(random_bytes(16)));
        $this->session->set(self::CSRF_TOKEN, $token);

        return $token;
    }

    private function csrfTokenIsValid(): bool
    {
        if (! $this->session->has(self::CSRF_TOKEN) || ! isset($this->csrf_token)) {
            return false;
        }

        $hash = $this->session->get(self::CSRF_TOKEN);
        if (! $this->config('csrf.persist')) {
            $this->session->set(self::CSRF_TOKEN, null);
        }

        if ($this->config('csrf.origin_check') && sha1($this->server('REMOTE_ADDR') . $this->server('HTTP_USER_AGENT')) != substr(base64_decode($hash), 10, 40)) {
            return false;
        }

        if ($this->csrf_token != $hash || $this->csrfTokenHasExpired($this->csrf_token)) {
            return false;
        }

        return true;
    }

    public function csrfTokenHasExpired(?string $token): bool
    {
        if (! empty($token)) {
            $validity = $this->config('session.timeout');

            return is_int($validity) && (intval(substr(base64_decode($token), 0, 10)) + $validity) < time();
        }

        return true;
    }

    private function checkCsrf(): bool
    {
        $csrf = $this->config('csrf');
        $check_csrf = $csrf['enable'];

        return $check_csrf && ! $this->routeExist($csrf['routes']['exclude'], $this->route());
    }

    private function checkHoneypot(): bool
    {
        return (bool) $this->config('csrf.honeypot');
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->getAuthToken();
        if (isset($auth)) {
            [$bearer, $token] = array_pad(explode(' ', $auth), 2, null);
            if ($bearer === 'Bearer') {
                return $token;
            }
        }

        return null;
    }

    public function getAuthToken(): ?string
    {
        return $this->header('authorization');
    }

    private function retrieve(string $source, ?string $key = null, mixed $default = null): mixed
    {
        if (! is_null($key)) {
            return $this->$source->get($key, $default);
        }

        return $this->$source->all();
    }

    private static function parseContent(array $post_data, string $stream_content): array
    {
        if (! empty($post_data)) {
            return $post_data;
        }
        parse_str($stream_content, $parsed);

        return $parsed;
    }

    private static function getInputContent(): string
    {
        return file_get_contents('php://input');
    }

    private function parseContentFromStream(string $payload): array
    {
        $content_type = $this->header('content-type') ?? '';

        if (str_contains($content_type, self::CONTENT_TYPE_JSON)) {
            return $this->parseJson($payload);
        }

        if (str_contains($content_type, self::CONTENT_TYPE_XML_APP) || str_contains($content_type, self::CONTENT_TYPE_XML_TEXT)) {
            return $this->parseXml($payload);
        }

        if (str_contains($content_type, self::CONTENT_TYPE_MULTIPART)) {
            return $this->parseMultipartFormData($payload);
        }

        return [];
    }

    private function parseJson(string $payload): array
    {
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $data;
    }

    private function parseXml(string $payload): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($payload, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            libxml_clear_errors();

            return [];
        }

        return json_decode(json_encode($xml), true) ?? [];
    }

    private function parseMultipartFormData(string $payload): array
    {
        $content_type = $this->header('content-type');
        $boundary = null;

        if (preg_match('/boundary="?([^";]+)"?/i', $content_type, $matches)) {
            $boundary = $matches[1];
        }

        if (! $boundary) {
            return [];
        }

        $parts = array_slice(explode('--' . $boundary, $payload), 1);
        $data = [];

        foreach ($parts as $part) {
            if (strpos($part, '--') === 0) {
                continue;
            }

            [$raw_headers, $body] = explode("\r\n\r\n", $part, 2);
            $headers = $this->parseHeaders($raw_headers);

            if (! isset($headers['Content-Disposition'])) {
                continue;
            }

            preg_match('/name="([^"]+)"/i', $headers['Content-Disposition'], $name_matches);

            if (! isset($name_matches[1])) {
                continue;
            }

            $name = $name_matches[1];
            if (isset($headers['Content-Type'])) {
                $data[$name] = [
                    'filename' => preg_match('/filename="([^"]+)"/i', $headers['Content-Disposition'], $file_matches) ? $file_matches[1] : null,
                    'content' => $body,
                ];
            } else {
                $data[$name] = rtrim($body, "\r\n");
            }
        }

        return $data;
    }

    private function parseHeaders(string $raw_headers): array
    {
        $headers = [];
        foreach (explode("\r\n", $raw_headers) as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $headers;
    }

    public function callback(): string
    {
        return $this->baseUrl($this->callback_route ?? $this->route());
    }

    public static function getCsrfTokenIdentifier(): string
    {
        return self::CSRF_TOKEN;
    }

    public static function getHoneypotIdentifier(): string
    {
        return self::HONEY_POT;
    }

    public static function getCallbackRouteIdentifier(): string
    {
        return self::CALLBACK_ROUTE;
    }

    public static function getMethodIdentifier(): string
    {
        return self::METHOD;
    }

    public function setHeader(string $key, string $value): void
    {
        $this->header->set($key, $value);
    }

    public function getUri(): Uri
    {
        return $this->uri;
    }

    public function withUri(Uri $uri, $preserve_host = false): self
    {
        $new = clone $this;
        $new->uri = $uri;
        if (! ($preserve_host && $new->hasHeader('Host')) && $uri->getHost()) {
            $new->withHeader('Host', $uri->getHost());
        }

        return $new;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocol_version;
    }

    public function withProtocolVersion(string $version): self
    {
        $new = clone $this;
        $new->protocol_version = $version;

        return $new;
    }

    public function getMethod(): string
    {
        return strtoupper($this->server->get('REQUEST_METHOD') ?? self::METHOD_GET);
    }

    public function withMethod(string $method): self
    {
        $new = clone $this;
        $new->server->set('REQUEST_METHOD', strtoupper($method));

        return $new;
    }

    public function getRequestTarget(): string
    {
        return $this->uri->getPath() . ($this->uri->getQuery() ? '?' . $this->uri->getQuery() : '');
    }

    public function contentTypeIs(string $type): bool
    {
        $contentType = $this->header('content-type');

        return $contentType && str_contains(strtolower($contentType), strtolower($type));
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('accept');

        if (empty($accept)) {
            return false;
        }

        return str_contains($accept, '/json') || str_contains($accept, '+json');
    }

    public function expectsJson(): bool
    {
        return $this->wantsJson();
    }

    public function accepts(string|array $contentTypes): bool
    {
        $acceptHeader = $this->header('accept');

        if (empty($acceptHeader)) {
            return false;
        }

        if ($acceptHeader === '*/*') {
            return true;
        }

        $contentTypes = (array) $contentTypes;

        foreach ($contentTypes as $type) {
            if (str_contains(strtolower($acceptHeader), strtolower($type))) {
                return true;
            }
        }

        return false;
    }

    public function withRequestTarget(mixed $request_target): self
    {
        $new = clone $this;
        $new->uri = new Uri($request_target);

        return $new;
    }

    public function getHeaders(): array
    {
        return $this->psr7_headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->psr7_headers[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $header_name = strtolower($name);

        return $this->psr7_headers[$header_name] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, mixed $value): self
    {
        $new = clone $this;
        $new->psr7_headers[strtolower($name)] = (array) $value;

        return $new;
    }

    public function withAddedHeader(string $name, mixed $value): self
    {
        $new = clone $this;
        $header_name = strtolower($name);
        $new->psr7_headers[$header_name] = array_merge($this->psr7_headers[$header_name] ?? [], (array) $value);

        return $new;
    }

    public function setAuthenticatedUser(mixed $user): self
    {
        $this->authenticatedUser = $user;

        return $this;
    }

    public function getAuthenticatedUser(): mixed
    {
        return $this->authenticatedUser;
    }

    public function user(): mixed
    {
        return $this->authenticatedUser;
    }

    public function setAuthToken(string $token): self
    {
        $this->authenticatedToken = $token;

        return $this;
    }

    public function token(): ?string
    {
        return $this->authenticatedToken;
    }

    public function withoutHeader(string $name): self
    {
        $new = clone $this;
        unset($new->psr7_headers[strtolower($name)]);

        return $new;
    }

    public function getBody(): Stream
    {
        return $this->body;
    }

    public function withBody(Stream $body): self
    {
        $new = clone $this;
        $new->body = $body;

        return $new;
    }
}
