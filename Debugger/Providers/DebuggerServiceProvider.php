<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * DebuggerServiceProvider registers the Debugger service and integrates it with the application.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Providers;

use Core\Services\DeferredServiceProvider;
use Debugger\Debugger;
use Debugger\DebuggerInterface;
use Helpers\File\FileLogger;
use Throwable;

class DebuggerServiceProvider extends DeferredServiceProvider
{
    public static function provides(): array
    {
        return [
            DebuggerInterface::class,
        ];
    }

    public function register(): void
    {
        $this->container->singleton(DebuggerInterface::class, function ($container) {
            return Debugger::getInstance($container);
        });
    }

    public function boot(): void
    {
        FileLogger::listen(function (array $logData) {
            try {
                $debugger = $this->container->get(DebuggerInterface::class);
                $label = match ($logData['level']) {
                    'error', 'critical' => 'error',
                    'warning' => 'warning',
                    default => 'info',
                };

                $message = $logData['message'];
                if (!empty($logData['context'])) {
                    $message .= ' ' . json_encode($logData['context'], JSON_UNESCAPED_SLASHES);
                }

                $debugger->push('messages', $message, $label);
            } catch (Throwable $e) {
            }
        });
    }
}
