<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * System Error Handler.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Error;

use Core\Services\ConfigServiceInterface;
use Core\Support\Adapters\Interfaces\EnvironmentInterface;
use Core\Support\Adapters\Interfaces\SapiInterface;
use ErrorException;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Helpers\Http\Request;
use Helpers\Http\Response;
use Throwable;

class ErrorHandler
{
    protected readonly Request $request;

    protected readonly ConfigServiceInterface $config;

    protected readonly SapiInterface $sapi;

    protected readonly PathResolverInterface $paths;

    protected readonly EnvironmentInterface $environment;

    protected readonly DebugHelper $debug;

    public static array $exceptionCallbacks = [];

    public const CONTENT_TYPE_JSON = 'application/json';
    public const CONTENT_TYPE_HTML = 'text/html';
    public const CONTENT_TYPE_PLAIN = 'text/plain';

    public function __construct(Request $request, ConfigServiceInterface $config, SapiInterface $sapi, PathResolverInterface $paths, EnvironmentInterface $environment, DebugHelper $debug)
    {
        $this->request = $request;
        $this->config = $config;
        $this->sapi = $sapi;
        $this->paths = $paths;
        $this->environment = $environment;
        $this->debug = $debug;

        $debug = $this->debugMode();

        error_reporting($debug ? E_ALL : 0);
        ini_set('display_errors', $debug ? 1 : 0);
        ini_set('log_errors', 1);

        ini_set('error_log', $this->paths->basePath('error.log'));
    }

    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (! (error_reporting() & $errno)) {
            return false;
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public function handleException(Throwable $e): void
    {
        error_log(sprintf("Uncaught %s: %s in %s on line %d\n%s", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));

        // Call registered callbacks for Watcher integration
        $exceptionData = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'code' => $e->getCode(),
        ];

        foreach (self::$exceptionCallbacks as $callback) {
            try {
                $callback($exceptionData);
            } catch (Throwable $callbackException) {
                // Prevent infinite loops if callback throws
                error_log('Watcher callback error: ' . $callbackException->getMessage());
            }
        }

        $this->renderException($e);
    }

    public static function listen(callable $callback): void
    {
        self::$exceptionCallbacks[] = $callback;
    }

    public function handleShutdown(): void
    {
        $lastError = error_get_last();

        if ($lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $fatalException = new FatalErrorException(
                'Fatal PHP Error: ' . $lastError['message'] . ' in ' . $lastError['file'] . ' on line ' . $lastError['line'],
                $lastError,
                $lastError['type']
            );

            $this->handleException($fatalException);
        }
    }

    private function renderException(Throwable $e): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $response = $this->prepareResponse($e);
        (new Response($response, 500, [
            'Content-Type' => $this->getContentType(),
        ]))->complete();
    }

    private function debugMode(): bool
    {
        return $this->config->isDebugEnabled();
    }

    private function getContentType(): string
    {
        if ($this->request->header('content-type') == self::CONTENT_TYPE_JSON) {
            return self::CONTENT_TYPE_JSON;
        }

        if (! $this->sapi->isCli()) {
            return self::CONTENT_TYPE_HTML;
        }

        return self::CONTENT_TYPE_PLAIN;
    }

    private function isJsonResponse(): bool
    {
        return $this->getContentType() === self::CONTENT_TYPE_JSON;
    }

    public function isCliResponse(): bool
    {
        return $this->getContentType() === self::CONTENT_TYPE_PLAIN;
    }

    public function isHtmlResponse(): bool
    {
        return $this->getContentType() === self::CONTENT_TYPE_HTML;
    }

    private function prepareResponse(Throwable $e): string
    {
        if ($this->isJsonResponse()) {
            return $this->asJson($e);
        }

        if ($this->isHtmlResponse()) {
            return $this->asHtml($e);
        }

        return $this->asString($e);
    }

    private function asHtml(Throwable $e): string
    {
        if (! $this->debugMode()) {
            $template = 'error500';
        } else {
            $template = 'error';
            $basePath = $this->paths->basePath();
            $isLocal = $this->environment->isLocal();
            $request = $this->request;
            $debug = $this->debug;
            $data = compact('e', 'basePath', 'request', 'isLocal', 'debug');
            ob_start();
            extract($data, EXTR_SKIP);
        }

        include $this->paths->corePath('Error' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $template . '.html');

        return ltrim(ob_get_clean());
    }

    private function asJson(Throwable $e): string
    {
        return json_encode($this->asString($e));
    }

    private function asString(Throwable $e): string
    {
        if (! $this->debugMode()) {
            return 'A critical error occurred. Please try again later.';
        }

        return $e->__toString();
    }
}
