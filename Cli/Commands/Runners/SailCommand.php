<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to run comprehensive production readiness checks.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Runners;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class SailCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('sail')
            ->setDescription('Run comprehensive pre-flight checks to ensure production readiness.')
            ->setHelp('This command runs repair checks, style inspections, and unit tests to ensure production readiness.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Production Readiness Checks');

        if (! $this->checkPrerequisites($io)) {
            return Command::FAILURE;
        }

        $io->section('1. Integrity & Style Inspection');
        $io->text('Running Coding Style (Pint) and Comment Cleanup Check...');

        // Start Pint and Comment Check
        $pintCommand = PHP_BINARY . ' vendor/bin/pint --test';
        $formatCommand = PHP_BINARY . ' dock format --check --skip-pint';

        $pintProcess = $this->runProcess($pintCommand, async: true);
        $formatProcess = $this->runProcess($formatCommand, async: true);

        // Wait for both to complete
        while ($pintProcess->isRunning() || $formatProcess->isRunning()) {
            usleep(100000);
        }

        // Check Pint results
        $io->newLine();
        $io->text('Coding Style (Pint) Results:');
        if (! $pintProcess->isSuccessful()) {
            $output->write($pintProcess->getOutput() ?: $pintProcess->getErrorOutput());
            $io->error('Coding Style (Pint) check failed! The process cannot continue.');

            return Command::FAILURE;
        }
        $io->success('Coding Style (Pint) Passed.');

        // Check Comment Results
        $io->newLine();
        $io->text('Comment Cleanup Check Results:');
        if (! $formatProcess->isSuccessful()) {
            $output->write($formatProcess->getOutput() ?: $formatProcess->getErrorOutput());
            $io->error('Comment Cleanup Check failed! Unnecessary comments detected. Please run "php dock format".');

            return Command::FAILURE;
        }
        $io->success('Comment Cleanup Check Passed.');

        $io->section('2. Operational Readiness & Testing');
        $pestCommand = PHP_BINARY . ' vendor/bin/pest --colors=always';
        if (! $this->runStep($io, $output, $pestCommand, 'Unit Tests')) {
            return Command::FAILURE;
        }

        $io->newLine();
        $io->success('All checks passed! The application is ready for the Port (deployment). 🚀');
        $io->writeln('<info>VOYAGE CLEAR! All Inspections, Repairs, and Unit Tests passed. Vessel is cleared to set sail for the Port.</info>');

        return Command::SUCCESS;
    }

    protected function runProcess(string $command, bool $async = false): Process
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(null);

        if ($async) {
            $process->start();
        } else {
            $process->run();
        }

        return $process;
    }

    private function checkPrerequisites(SymfonyStyle $io): bool
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $binaries = [
            'pint' => 'vendor/bin/pint',
            'pest' => 'vendor/bin/pest',
        ];

        $missing = [];

        foreach ($binaries as $name => $binary) {
            // Check for the binary with platform-specific extensions
            $exists = file_exists($binary) || ($isWindows && file_exists($binary . '.bat')) || file_exists($binary . '.exe');

            if (! $exists) {
                $missing[] = $name;
            }
        }

        if (! empty($missing)) {
            $io->error('Missing required dependencies: ' . implode(', ', $missing));
            $io->note('Please run "composer install" to install all required dependencies.');

            return false;
        }

        return true;
    }

    private function runStep(SymfonyStyle $io, OutputInterface $output, string $command, string $name): bool
    {
        $io->text("running {$name}...");

        $process = $this->runProcess($command, async: true);
        $process->setTty(Process::isTtySupported());

        $process->wait(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $io->error("{$name} check failed! The process cannot continue.");

            return false;
        }

        $io->success("{$name} Passed.");

        return true;
    }
}
