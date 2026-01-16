<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Response provides a simple and flexible way to construct and send HTTP responses.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http;

use Exception;
use finfo;
use InvalidArgumentException;
use SplFileObject;
use UnexpectedValueException;

class Response
{
    use \Helpers\Macroable;

    private static bool $is_redirect = false;

    private array $message = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Unused',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m A Teapot',
        419 => 'Authentication Timeout',
        420 => 'Enhance Your Calm',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'No Response',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
        451 => 'Unavailable For Legal Reasons',
        494 => 'Request Header Too Large',
        495 => 'Cert Error',
        496 => 'No Cert',
        497 => 'HTTP to HTTPS',
        499 => 'Client Closed Request',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        598 => 'Network Read Timeout Error',
        599 => 'Network Connect Timeout Error',
    ];

    protected $header;

    protected $body;

    protected $status;

    protected $reason;

    public function __construct(string $data = '', int $status = 200, array $header = [])
    {
        $this->header = new Header($header);
        $this->status = $status;
        $this->reason = $this->reason($status);

        try {
            $this->body($data);
        } catch (UnexpectedValueException $exception) {
            throw $exception;
        }
    }

    public function header(array $header): self
    {
        foreach ($header as $name => $value) {
            $this->header->set($name, $value);
        }

        return $this;
    }

    public function status(int $status, string $reason = ''): self
    {
        $this->status = $status;
        $this->reason = $reason ?: $this->reason($status);

        return $this;
    }

    public function setStatusCode(int $status): self
    {
        return $this->status($status);
    }

    public function setHeader(string $name, string $value): self
    {
        $this->header->set($name, $value);

        return $this;
    }

    public function body($body): self
    {
        if (! is_null($body) && ! is_string($body) && ! is_numeric($body) && ! is_callable([$body, '__toString'])) {
            throw new UnexpectedValueException(
                'The response body must be a string or object implementing __toString() given ' . gettype($body)
            );
        }

        if (is_object($body) && method_exists($body, '__toString')) {
            $body = (string) $body;
        }

        $this->body = $body;

        return $this;
    }

    public function ok(string $data = ''): self
    {
        return $this->status(200)->body($data);
    }

    public function created(?string $location = null): self
    {
        $response = $this->status(201);
        if ($location) {
            $response->header(['Location' => $location]);
        }

        return $response;
    }

    public function notFound(string $body = 'Not Found'): self
    {
        return $this->status(404)->body($body);
    }

    public function forbidden(string $body = 'Forbidden'): self
    {
        return $this->status(403)->body($body);
    }

    public function getContent(): mixed
    {
        return $this->body;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getReasonPhrase(): string
    {
        return $this->reason;
    }

    public function getHeaderBag(): Header
    {
        return $this->header;
    }

    public function getHeaders(): array
    {
        return $this->header->all();
    }

    public function getHeader(string $name): mixed
    {
        return $this->header->get($name);
    }

    protected function sendHeaders(): void
    {
        if (! headers_sent()) {
            if (static::$is_redirect) {
                header('Location: ' . $this->header->get('location'));
            }

            if (! static::$is_redirect) {
                $status = $this->status;
                $server = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';

                header(sprintf('%s %s %s', $server, $status, $this->reason), true, $status);

                foreach ($this->header->all() as $name => $value) {
                    header($name . ':' . $value, strcasecmp($name, 'Content-Type') === 0, $status);
                }
            }
        }
    }

    protected function sendContent(): void
    {
        echo $this->body;
    }

    public function send(): void
    {
        $this->sendHeaders();
        $this->sendContent();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif ('cli' !== PHP_SAPI) {
            static::closeOutputBuffers(0, true);
        }
    }

    public function end(?callable $callback = null): void
    {
        if ($callback !== null && is_callable($callback)) {
            call_user_func($callback);
        }

        exit;
    }

    public function complete(?callable $callback = null): void
    {
        $this->send();
        $this->end($callback);
    }

    public function reason(int $status): string
    {
        if ($status < 99 || $status > 599) {
            $status = 500;
        }

        return $this->message[$status] ?? 'Unknown';
    }

    public static function closeOutputBuffers(int $targetLevel, bool $flush): void
    {
        $status = ob_get_status(true);
        $level = count($status);
        $flags = PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE);

        while ($level-- > $targetLevel && ($s = $status[$level]) && (! isset($s['del']) ? ! isset($s['flags']) || ($s['flags'] & $flags) === $flags : $s['del'])) {
            if ($flush) {
                ob_end_flush();
            } else {
                ob_end_clean();
            }
        }
    }

    public function json(array $data, int $option = 0): self
    {
        $data = $this->encode($data, $option);

        $this->body($data);
        $this->header(['Content-Type' => 'application/json; charset=UTF-8']);

        return $this;
    }

    private function encode(array $data, int $option): string
    {
        try {
            $data = json_encode($data, $option);
            if (! $this->validate(json_last_error(), $option)) {
                throw new InvalidArgumentException(json_last_error_msg());
            }
        } catch (Exception $exception) {
            if (get_class($exception) === 'Exception' && mb_strpos($exception->getMessage(), 'Failed calling ') === 0) {
                throw $exception->getPrevious() ?: $exception;
            }

            throw $exception;
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(json_last_error_msg());
        }

        return $data;
    }

    private function validate(int $error, int $option): bool
    {
        if ($error === JSON_ERROR_NONE) {
            return true;
        }

        return ($option & JSON_PARTIAL_OUTPUT_ON_ERROR) && in_array($error, [
            JSON_ERROR_RECURSION,
            JSON_ERROR_INF_OR_NAN,
            JSON_ERROR_UNSUPPORTED_TYPE,
        ]);
    }

    public function back(): self
    {
        $url = ($_SERVER['HTTP_REFERER'] ?? '/');

        return $this->redirect($url);
    }

    public function redirect(string $url, int $status = 302): self
    {
        static::$is_redirect = true;

        return new Response(static::content($url), $status, ['Location' => $url]);
    }

    public function permanentRedirect(string $url): self
    {
        return $this->redirect($url, 301);
    }

    public function seeOther(string $url): self
    {
        return $this->redirect($url, 303);
    }

    private static function content(string $url): string
    {
        return
            sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=%1$s" />

        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'));
    }

    public function download(string $filePath, ?string $fileName = null): void
    {
        $this->file($filePath, false, $fileName);
    }

    public function file(string $filePath, bool $inline = false, ?string $fileName = null): void
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException(sprintf('File not found or not readable: "%s"', $filePath));
        }

        $fileName = $fileName ?? basename($filePath);
        $mimeType = $this->getMimeType($filePath) ?: 'application/octet-stream';
        $fileSize = filesize($filePath);
        $disposition = $inline ? 'inline' : 'attachment';

        $this->status(200);
        $this->header([
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, rawurlencode($fileName)),
            'Cache-Control' => 'public, must-revalidate',
            'Pragma' => 'public',
            'Expires' => '0',
        ]);

        $this->body = null;

        $this->sendHeaders();

        if (! headers_sent()) {
            $file = new SplFileObject($filePath, 'rb');
            while (! $file->eof()) {
                echo $file->fread(1024 * 8);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            $this->end();
        }
    }

    protected function getMimeType(string $filePath): string|false
    {
        if (class_exists(finfo::class)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            return $finfo->file($filePath);
        }

        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }

        return false;
    }

    public function isInformational(int $code): bool
    {
        return $code >= 100 && $code < 200;
    }

    public function isSuccessful(int $code): bool
    {
        return $code >= 200 && $code < 300;
    }

    public function isRedirect(int $code): bool
    {
        return $code >= 300 && $code < 400;
    }

    public function isClientError(int $code): bool
    {
        return $code >= 400 && $code < 500;
    }

    public function isServerError(int $code): bool
    {
        return $code >= 500 && $code < 600;
    }

    public function isError(int $code): bool
    {
        return $this->isClientError($code) || $this->isServerError($code);
    }
}
