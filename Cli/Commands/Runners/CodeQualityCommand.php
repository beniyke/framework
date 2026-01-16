<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to inspect code quality using PHPStan and Pint.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Runners;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class CodeQualityCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('inspect')
            ->setDescription('Inspects the code for quality assurance using PHPStan.')
            ->setHelp('This command runs PHPStan analysis on App and System directories.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Code Quality Inspection');

        $hasFailure = false;

        $io->section('1. Coding Style (Pint)');
        $pintCmd = PHP_BINARY . ' vendor/bin/pint --test';
        if (! $this->runStep($output, $pintCmd)) {
            $io->error('Coding style violations found.');
            $hasFailure = true;
        } else {
            $io->success('Coding style passed.');
        }

        $io->section('2. Static Analysis (PHPStan)');
        $stanCmd = PHP_BINARY . ' -d memory_limit=2G vendor/bin/phpstan analyse -c phpstan.neon --ansi';
        if (! $this->runStep($output, $stanCmd)) {
            $io->error('Static analysis failed.');
            $hasFailure = true;
        } else {
            $io->success('Static analysis passed.');
        }

        if ($hasFailure) {
            $io->error('Inspection failed. Please fix the reported issues.');

            return Command::FAILURE;
        }

        $io->success('All code quality checks passed successfully!');

        return Command::SUCCESS;
    }

    private function runStep(OutputInterface $output, string $command): bool
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());

        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        return $process->isSuccessful();
    }
}
