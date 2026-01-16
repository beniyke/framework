<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to setup development environment (Server, Worker, Watcher).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Runners;

use Cli\Services\DevReadinessService;
use Core\Support\Environment;
use Exception;
use Helpers\File\FileSystem;
use Helpers\File\Paths;
use LogicException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class DevSetupCommand extends Command
{
    private ?Process $server_process = null;

    private ?Process $worker_process = null;

    private ?Process $watcher_process = null;

    protected function configure(): void
    {
        $this->setName('dev')
            ->setDescription('Starts the PHP built-in server and worker processes in the background, and restarts them on .env file changes.')
            ->setHelp('This command starts the PHP server on ' . env('APP_HOST') . ' and runs a worker process, both in the background and restarts them on .env file changes. It is intended for use in the development environment only.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Development Environment Setup (PHP Server, Worker, & Watcher)');

        try {
            if (Environment::isProduction()) {
                throw new LogicException('This command can only be run in the "dev" environment.');
            }

            $env_file = Paths::basePath('.env');

            if (! FileSystem::exists($env_file)) {
                $io->error('The .env configuration file does not exist. Cannot start development setup.');

                return self::FAILURE;
            }

            $last_modified = FileSystem::lastModified($env_file);

            // Execute Readiness Checks
            $readinessService = new DevReadinessService();
            if (! $readinessService->runChecks($io)) {
                return self::FAILURE;
            }

            $this->flushCache($io);
            $this->startProcesses($io);

            $io->section('File Watcher Started');
            $io->note('Monitoring ' . basename($env_file) . ' for changes (stable loop)...');

            while (true) {
                if ($this->server_process && ! $this->server_process->isRunning()) {
                    $io->warning('PHP Server process stopped unexpectedy.');
                    if ($error = $this->server_process->getErrorOutput()) {
                        $io->block($error, 'SERVER ERROR', 'fg=white;bg=red', ' ', true);
                    }

                    $io->text('Restarting server process...');
                    $this->startServer($io);
                    sleep(2); // Prevent rapid crash loops
                }

                if ($this->worker_process && ! $this->worker_process->isRunning()) {
                    $io->warning('Worker process stopped unexpectedly.');
                    if ($error = $this->worker_process->getErrorOutput()) {
                        $io->block($error, 'WORKER ERROR', 'fg=white;bg=red', ' ', true);
                    }

                    $io->text('Restarting worker process...');
                    $this->startWorker($io);
                    sleep(2); // Prevent rapid crash loops
                }

                clearstatcache(true, $env_file);
                $current_modified = FileSystem::lastModified($env_file);

                if ($current_modified > $last_modified) {
                    $io->caution('.env file detected! Restarting processes...');
                    $last_modified = $current_modified;

                    $this->stopProcesses($io);
                    $this->flushCache($io);
                    $this->startProcesses($io);
                    $io->text('Monitoring resumed...');
                }

                usleep(500000);
            }

            return self::SUCCESS;
        } catch (LogicException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            $io->error('An unexpected fatal error occurred: ' . $e->getMessage());
            $this->stopProcesses($io);

            return self::FAILURE;
        }
    }

    private function startProcesses(SymfonyStyle $io): void
    {
        $io->section('Starting Processes');

        $this->startServer($io);
        $this->startWorker($io);
    }

    private function startServer(SymfonyStyle $io): void
    {
        $cwd = Paths::basePath();
        $appHost = env('APP_HOST');

        $this->server_process = new Process(['php', '-S', $appHost, 'server.php'], $cwd);
        $this->server_process->start();

        if ($this->server_process->isRunning()) {
            $io->success(sprintf('PHP server running at <info>http://%s</info>', $appHost));
        } else {
            $io->error('Failed to start PHP server.');
        }
    }

    private function startWorker(SymfonyStyle $io): void
    {
        $cwd = Paths::basePath();

        // Correctly start the worker with the 'start' command
        $this->worker_process = new Process(['php', 'worker', 'start'], $cwd);
        $this->worker_process->start();

        if ($this->worker_process->isRunning()) {
            $io->success('PHP worker started successfully.');
        } else {
            $io->error('Failed to start PHP worker.');
        }
    }

    private function stopProcesses(SymfonyStyle $io): void
    {
        $io->text('Stopping active processes...');
        $timeout = 10; // Increased timeout for graceful worker shutdown

        if ($this->server_process && $this->server_process->isRunning()) {
            $this->server_process->stop($timeout);
            $status = $this->server_process->isRunning() ? '<fg=red>forcefully killed</>' : '<fg=green>stopped gracefully</>';
            $io->text("PHP Server {$status}.");
            $this->server_process = null;
        }

        if ($this->worker_process && $this->worker_process->isRunning()) {
            $this->worker_process->stop($timeout);
            $status = $this->worker_process->isRunning() ? '<fg=red>forcefully killed</>' : '<fg=green>stopped gracefully</>';
            $io->text("PHP Worker {$status}.");
            $this->worker_process = null;
        }

        if ($this->watcher_process && $this->watcher_process->isRunning()) {
            $this->watcher_process->stop($timeout);
            $this->watcher_process = null;
        }
    }

    private function flushCache(SymfonyStyle $io): void
    {
        $io->text('Flushing application cache...');
        $cachePath = Paths::storagePath('cache');

        if (FileSystem::exists($cachePath)) {
            if (FileSystem::delete($cachePath, true)) {
                $io->success('Application cache flushed successfully.');
            } else {
                $io->warning('Failed to flush application cache. Please check file permissions.');
            }
        }
    }
}
