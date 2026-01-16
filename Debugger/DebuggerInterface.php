<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for the Debugger class.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger;

use Core\Ioc\ContainerInterface;
use DebugBar\DebugBar;
use DebugBar\JavascriptRenderer;

interface DebuggerInterface
{
    public static function getInstance(ContainerInterface $container): DebuggerInterface;

    public function push(string $collectorName, string $message, string $label = 'info'): void;

    public function renderer(): JavascriptRenderer;

    public function getDebugBar(): DebugBar;
}
