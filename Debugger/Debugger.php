<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Debugger handles debugging with DebugBar.
 * This singleton class wraps the StandardDebugBar and manages collectors.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger;

use Core\Ioc\ContainerInterface;
use Core\Services\ConfigServiceInterface;
use Core\Support\Adapters\Interfaces\EnvironmentInterface;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DebugBar;
use DebugBar\JavascriptRenderer;
use Debugger\Collector\ConfigCollector;
use Debugger\Collector\EnvironmentCollector;
use Debugger\Collector\MessagesCollector;
use Debugger\Collector\ModelCollector;
use Debugger\Collector\QueryCollector;
use Debugger\Collector\RequestCollector;
use Debugger\Collector\ResponseCollector;
use Debugger\Collector\SessionCollector;
use Debugger\Collector\TimelineCollector;
use Debugger\Collector\ViewCollector;
use Helpers\Http\Request;
use Helpers\Http\Session;
use InvalidArgumentException;
use RuntimeException;

class Debugger implements DebuggerInterface
{
    private static ?DebuggerInterface $instance = null;

    private DebugBar $debugbar;

    private JavascriptRenderer $renderer;

    private ContainerInterface $container;

    private const DEBUG_BAR_ASSET_PATH = 'libs/DebugBar/Resources/';

    private function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->debugbar = new DebugBar();

        $this->debugbar->addCollector(new PhpInfoCollector());
        $this->debugbar->addCollector(new MemoryCollector());
        $this->debugbar->addCollector(new ExceptionsCollector());

        $this->registerCollectors();

        $this->renderer = $this->debugbar->getJavascriptRenderer(url(self::DEBUG_BAR_ASSET_PATH));

        $this->renderer->addAssets(
            ['widgets/sqlqueries/widget.css'],
            ['widgets/sqlqueries/widget.js']
        );
    }

    private function registerCollectors(): void
    {
        $this->debugbar->addCollector(new QueryCollector());

        $requestCollector = new RequestCollector($this->container->get(Request::class));
        $this->debugbar->addCollector($requestCollector, 'http_request');

        $this->debugbar->addCollector(new SessionCollector($this->container->get(Session::class)));
        $this->debugbar->addCollector(new ConfigCollector($this->container->get(ConfigServiceInterface::class)));
        $this->debugbar->addCollector(new EnvironmentCollector($this->container->get(EnvironmentInterface::class)));
        $this->debugbar->addCollector(new ResponseCollector());
        $this->debugbar->addCollector(new TimelineCollector());
        $this->debugbar->addCollector(new MessagesCollector());
        $this->debugbar->addCollector(new ModelCollector());
        $this->debugbar->addCollector(new ViewCollector());
    }

    public static function getInstance(?ContainerInterface $container = null): DebuggerInterface
    {
        if (self::$instance === null) {
            if ($container === null) {
                throw new RuntimeException('Debugger not initialized and no container provided.');
            }

            self::$instance = new Debugger($container);
        }

        return self::$instance;
    }

    public function push(string $collectorName, string $message, string $label = 'info'): void
    {
        try {
            $collector = $this->debugbar->getCollector($collectorName);

            if ($collector instanceof MessagesCollector) {
                $collector->addMessage($message, $label);
            } else {
                $this->logWarning("Attempted to push message to non-message collector: '{$collectorName}'");
            }
        } catch (InvalidArgumentException $e) {
            $this->logWarning("DebugBar collector not found: '{$collectorName}'");
        }
    }

    public function getDebugBar(): DebugBar
    {
        return $this->debugbar;
    }

    public function renderer(): JavascriptRenderer
    {
        return $this->renderer;
    }

    private function logWarning(string $message): void
    {
        error_log("[DEBUGGER WARNING]: {$message}");
    }
}
