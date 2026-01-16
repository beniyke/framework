<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Simple file-based logger implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File;

use Helpers\File\Contracts\LoggerInterface;

class FileLogger implements LoggerInterface
{
    private string $logFile;

    public static array $logCallbacks = [];

    private const DEFAULT_LOG_FILE = 'App/storage/logs/anchor.log';

    public function __construct(?string $logFile = null)
    {
        $fileToUse = $logFile ?? self::DEFAULT_LOG_FILE;

        $this->setLogFile($fileToUse);
    }

    /**
     * Sets the log file path and ensures the necessary directory structure exists.
     */
    public function setLogFile(string $logFile): self
    {
        $path = Paths::basePath($logFile);
        $directory = dirname($path);

        FileSystem::mkdir($directory);
        $this->logFile = $logFile;

        return $this;
    }

    /**
     * Gets the current log file path.
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Writes a log entry to the file.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $entry = sprintf("[%s] %s: %s%s\n", $timestamp, strtoupper($level), $message, $contextStr);

        FileSystem::append($this->logFile, $entry);

        // Call registered callbacks for Watcher integration
        $logData = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => $timestamp,
        ];

        foreach (self::$logCallbacks as $callback) {
            $callback($logData);
        }
    }

    public static function listen(callable $callback): void
    {
        self::$logCallbacks[] = $callback;
    }

    /**
     * Logs an entry at the ERROR level.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Logs an entry at the WANRNING level.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Logs an entry at the CRITICAL level.
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Logs an entry at the INFO level.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Logs an entry at the DEBUG level.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
}
