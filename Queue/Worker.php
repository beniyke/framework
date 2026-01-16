<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Worker class is a process manager that can be used to run long-running PHP scripts, jobs,
 * or tasks in the background as a daemon process.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue;

use Core\Support\Adapters\Interfaces\OSCheckerInterface;
use Exception;
use Helpers\File\Contracts\CacheInterface;
use Queue\Interfaces\QueueDispatcherInterface;
use RuntimeException;

if (! function_exists('posix_kill') && ! function_exists('pcntl_signal')) {
    function posix_kill($pid, $sig)
    {
        return false;
    }

    function pcntl_signal($sig, $handler)
    {
        return false;
    }

    function pcntl_signal_dispatch()
    {
        return false;
    }

    define('SIGTERM', 15);
}

class Worker
{
    private const CACHE_KEY_PREFIX = 'worker_status_';
    private const RESTART_COMMAND = 'restart';
    private const PAUSE_COMMAND = 'pause';

    private CacheInterface $cache;

    private OSCheckerInterface $osChecker;

    private int $timeout;

    private int $sleep;

    private int $memoryLimit;

    private int $maxProcesses;

    private string $queueName;

    protected bool $running = true;

    private bool $checkState;

    private ?QueueDispatcherInterface $dispatcher = null;

    private array $childPids = [];

    public static array $jobCallbacks = [];

    public function __construct(CacheInterface $cache, OSCheckerInterface $osChecker, string $queueName = 'default', int $timeout = 300, int $sleep = 15, int $memoryLimit = 128, int $maxProcesses = 5, bool $checkState = true)
    {
        $this->cache = $cache->withPath('worker');
        $this->osChecker = $osChecker;
        $this->queueName = $queueName;
        $this->timeout = $timeout;
        $this->sleep = $sleep;
        $this->memoryLimit = $memoryLimit; // MB
        $this->maxProcesses = $maxProcesses;
        $this->checkState = $checkState;
    }

    private function getCacheKey(): string
    {
        return self::CACHE_KEY_PREFIX . $this->queueName;
    }

    public function start(): void
    {
        if ($this->hasStarted()) {
            throw new RuntimeException("Worker '{$this->queueName}' is already running. Use stop or restart command.");
        }

        ignore_user_abort(true);

        if (! $this->osChecker->isWindows()) {
            pcntl_signal(SIGTERM, [$this, 'signalHandler']);
            pcntl_signal(SIGINT, [$this, 'signalHandler']);
        }

        $this->daemon();
    }

    public function stop(): void
    {
        if ($this->hasStarted()) {
            $this->cache->write($this->getCacheKey(), '');
            echo "Waiting for worker '{$this->queueName}' to terminate..." . PHP_EOL;

            $startTime = time();

            while ($this->cache->has($this->getCacheKey())) {
                $this->sleep(1);
                if ((time() - $startTime) > $this->timeout) {
                    exit("Error: Worker '{$this->queueName}' termination timed out. You may need to manually kill the process.");
                }
            }
            exit('Process terminated successfully.');
        }
        exit('Worker has not been started');
    }

    public function restart(): bool
    {
        if (! $this->hasStarted()) {
            echo "Worker '{$this->queueName}' is not running.";

            return false;
        }

        $this->cache->write($this->getCacheKey(), self::RESTART_COMMAND);
        $startTime = time();

        while ($this->cache->has($this->getCacheKey())) {
            $this->sleep(1);
            if ((time() - $startTime) > $this->timeout) {
                echo "Error: Worker '{$this->queueName}' restart timed out.";

                return false;
            }
        }

        return true;
    }

    public function status(): string
    {
        if ($this->hasStarted()) {
            $content = $this->cache->read($this->getCacheKey());

            if ($content === self::PAUSE_COMMAND) {
                return "Worker '{$this->queueName}' is PAUSED. Use 'queue:resume --identifier={$this->queueName}' to continue.";
            }

            return $content !== self::RESTART_COMMAND ?
                "Worker '{$this->queueName}' is running. Last runtime was " . $content :
                "Worker '{$this->queueName}' is restarting...";
        }

        return "No active process running. Worker '{$this->queueName}' has not been started";
    }

    private function daemon(): void
    {
        // Ensure the worker is registered as running immediately
        if (! $this->hasStarted()) {
            $this->cache->write($this->getCacheKey(), 'started');
        }

        while ($this->running) {
            $status = $this->cache->read($this->getCacheKey());

            if ($status === '' || $status === null) {
                $this->terminate(); // Handles stop
            }

            if ($this->checkState) {
                if ($status === self::RESTART_COMMAND) {
                    $this->handleRestart();
                    $this->cache->write($this->getCacheKey(), 'started');

                    continue;
                }

                if ($status === self::PAUSE_COMMAND) {
                    $this->handlePause();

                    continue;
                }
            }

            $this->memoryExceeded() ? $this->timeout() : $this->execute();

            if (! $this->osChecker->isWindows()) {
                pcntl_signal_dispatch();
            }

            usleep(100000);
        }

        $this->terminate();
    }

    private function handlePause(): void
    {
        $this->cache->write($this->getCacheKey(), self::PAUSE_COMMAND);
        $this->sleep(5);

        if (! $this->osChecker->isWindows()) {
            pcntl_signal_dispatch();
        }
    }

    private function execute(): void
    {
        $this->cleanUpChildren();

        $activeProcesses = count($this->childPids);

        while ($activeProcesses < $this->maxProcesses && $this->running) {
            if (! $this->osChecker->isWindows()) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    error_log('Could not fork process.');
                    break;
                } elseif ($pid === 0) {
                    $this->processTask();
                    exit(0);
                } else {
                    $this->childPids[] = $pid;
                    $activeProcesses++;
                }
            } else {
                $this->processTask();
                break;
            }
        }

        if ($activeProcesses === 0 && $this->running) {
            $this->sleep($this->sleep);
        }
    }

    private function processTask(): void
    {
        $this->dispatcher = null;
        $startTime = microtime(true);
        $jobData = [
            'queue' => $this->queueName,
            'pid' => getmypid(),
            'started_at' => date('Y-m-d H:i:s'),
        ];

        foreach (self::$jobCallbacks as $callback) {
            $callback('started', $jobData);
        }

        try {
            echo 'Processing task in child process (PID: ' . getmypid() . ") for queue '{$this->queueName}'." . PHP_EOL;

            for ($i = 0; $i < 5; $i++) {
                if (! $this->running || $this->hasStopped() || $this->isRestarting()) {
                    $reason = $this->hasStopped() ? 'stop command' : ($this->isRestarting() ? 'restart command' : 'signal');
                    echo 'Child (PID: ' . getmypid() . ") exiting due to {$reason}." . PHP_EOL;

                    return;
                }

                $this->run();
                echo 'Child process (PID: ' . getmypid() . ") is working on queue '{$this->queueName}'..." . PHP_EOL;
                usleep(500000);
            }

            echo 'Child process (PID: ' . getmypid() . ") finished its cycle for queue '{$this->queueName}'." . PHP_EOL;

            $jobData['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            $jobData['status'] = 'completed';
            foreach (self::$jobCallbacks as $callback) {
                $callback('completed', $jobData);
            }
        } catch (Exception $e) {
            error_log('Error in child process (PID: ' . getmypid() . '): ' . $e->getMessage());

            $jobData['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            $jobData['status'] = 'failed';
            $jobData['error'] = $e->getMessage();
            foreach (self::$jobCallbacks as $callback) {
                $callback('failed', $jobData);
            }
        }
    }

    public static function listen(callable $callback): void
    {
        self::$jobCallbacks[] = $callback;
    }

    protected function run(): void
    {
        $this->cache->write($this->getCacheKey(), date('Y-m-d H:i:s'));

        try {
            $dispatcher = $this->getDispatcher();
            $response = $this->dispatchJobs($dispatcher);
        } catch (Exception $e) {
            $response = 'Error: ' . $e->getMessage();
        }

        echo $response . PHP_EOL;
    }

    private function dispatchJobs(QueueDispatcherInterface $dispatcher): string
    {
        $failedResponse = $dispatcher->failed($this->queueName)->run();
        echo "Failed Jobs (Queue: {$this->queueName}): " . $failedResponse . PHP_EOL;

        $pendingResponse = $dispatcher->pending($this->queueName)->run();

        return "Pending Jobs (Queue: {$this->queueName}): " . $pendingResponse;
    }

    private function cleanUpChildren(): void
    {
        if ($this->osChecker->isWindows()) {
            return;
        }

        while (count($this->childPids) > 0) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);

            if ($pid > 0) {
                $this->childPids = array_filter($this->childPids, fn ($p) => $p !== $pid);
            } elseif ($pid === 0) {
                break;
            } elseif ($pid === -1) {
                $this->childPids = [];
                break;
            }
        }
    }

    private function handleRestart(): void
    {
        echo "Worker '{$this->queueName}' is restarting..." . PHP_EOL;
        $this->terminateChildren();
        $this->sleep(5);

        if ($this->cache->delete($this->getCacheKey())) {
            echo "Worker '{$this->queueName}' has restarted. Initializing process..." . PHP_EOL;
        }
    }

    private function signalHandler(int $signal): void
    {
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
                $this->running = false;
                echo "Shutdown signal received. Stopping the worker '{$this->queueName}'..." . PHP_EOL;
                break;
        }
    }

    private function timeout(): void
    {
        echo "Memory limit exceeded. Taking a break for {$this->timeout} seconds on worker '{$this->queueName}'..." . PHP_EOL;
        $this->sleep($this->timeout);
    }

    private function terminateChildren(): void
    {
        if ($this->osChecker->isWindows()) {
            return;
        }

        foreach ($this->childPids as $pid) {
            if (! posix_kill($pid, SIGTERM)) {
                error_log("Could not signal child PID: {$pid} for termination.");
            }
        }

        $startTime = time();
        while (count($this->childPids) > 0 && (time() - $startTime) < 10) {
            $this->cleanUpChildren();
            $this->sleep(1);
        }

        foreach ($this->childPids as $pid) {
            error_log("Child PID {$pid} failed to terminate gracefully. Sending SIGKILL.");
            posix_kill($pid, SIGKILL);
        }

        $this->cleanUpChildren();
    }

    protected function terminate(): void
    {
        $this->terminateChildren();

        if ($this->cache->has($this->getCacheKey())) {
            $this->cache->delete($this->getCacheKey());
        }
        exit("Process for worker '{$this->queueName}' terminated");
    }

    private function getDispatcher(): QueueDispatcherInterface
    {
        if ($this->dispatcher === null) {
            if (! function_exists('resolve')) {
                throw new RuntimeException("Global 'resolve()' function required to instantiate QueueDispatcherInterface.");
            }
            $this->dispatcher = resolve(QueueDispatcherInterface::class);
        }

        return $this->dispatcher;
    }

    private function hasStarted(): bool
    {
        return $this->cache->has($this->getCacheKey());
    }

    private function hasStopped(): bool
    {
        $content = $this->cache->read($this->getCacheKey());

        return ! $this->cache->has($this->getCacheKey()) || $content === '' || $content === null;
    }

    private function isRestarting(): bool
    {
        return $this->hasStarted() && $this->cache->read($this->getCacheKey()) === self::RESTART_COMMAND;
    }

    private function isPaused(): bool
    {
        return $this->hasStarted() && $this->cache->read($this->getCacheKey()) === self::PAUSE_COMMAND;
    }

    private function memoryExceeded(): bool
    {
        return memory_get_usage(true) >= ($this->memoryLimit * 1024 * 1024);
    }

    protected function sleep(int $seconds): void
    {
        sleep($seconds);
    }
}
