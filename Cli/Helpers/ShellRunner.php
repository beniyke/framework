<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Helper for executing shell commands.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Helpers;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ShellRunner
{
    public static function execute($command, array $env = []): array
    {
        if (is_array($command)) {
            $process = new Process($command);
        } else {
            $process = Process::fromShellCommandline($command);
        }

        if (! empty($env)) {
            $process->setEnv($env);
        }

        $process->setTimeout(3600);
        $exitCode = 0;
        $errorOutput = '';
        $output = '';
        $success = false;

        try {
            $process->run();
            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();
            $exitCode = $process->getExitCode() ?? 0;
            $success = $process->isSuccessful();
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Shell execution failed catastrophically (e.g., timeout, command not found): ' . $e->getMessage() .
                    "\nCommand: " . $process->getCommandLine()
            );
        }

        if (! $success) {
            $errorMessage = $errorOutput ?: $output;
            if (empty($errorMessage)) {
                $errorMessage = "Command exited with non-zero status code ({$exitCode}). No error output captured.";
            }

            return [
                'success' => false,
                'output' => $output,
                'error' => trim($errorMessage),
                'command_line' => $process->getCommandLine(),
                'exit_code' => $exitCode,
            ];
        }

        return [
            'success' => true,
            'output' => trim($output),
            'error' => '',
            'command_line' => $process->getCommandLine(),
            'exit_code' => $exitCode,
        ];
    }
}
