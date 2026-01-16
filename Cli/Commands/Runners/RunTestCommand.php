<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to run the application test suite.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Runners;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class RunTestCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('test')
            ->setDescription('Run tests')
            ->setHelp('This command runs your test suite.')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to specific test file or directory')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter tests by name')
            ->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Run tests from specific group')
            ->addOption('coverage', null, InputOption::VALUE_NONE, 'Generate code coverage report')
            ->addOption('parallel', 'p', InputOption::VALUE_NONE, 'Run tests in parallel')
            ->addOption('colors', null, InputOption::VALUE_NONE, 'Force color output, even if TTY is not supported.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Running Tests');

        try {
            $path = $input->getArgument('path') ?? '';
            $filter = $input->getOption('filter');
            $group = $input->getOption('group');
            $coverage = $input->getOption('coverage');
            $parallel = $input->getOption('parallel');
            $colors = $input->getOption('colors');

            // Build the Pest command
            $command = 'php vendor/bin/pest';

            if (! empty($path)) {
                $command .= ' ' . escapeshellarg($path);
            }

            if ($filter) {
                $command .= ' --filter=' . escapeshellarg($filter);
            }

            if ($group) {
                $command .= ' --group=' . escapeshellarg($group);
            }

            if ($coverage) {
                $command .= ' --coverage';
            }

            if ($parallel) {
                $command .= ' --parallel';
            }

            if ($colors || $output->isDecorated()) {
                $command .= ' --colors=always';
            }

            $io->text('Executing: ' . $command);
            $io->newLine();

            $process = Process::fromShellCommandline($command);
            $process->setTimeout(null);
            $process->setTty(Process::isTtySupported());

            $exitCode = $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

            if ($exitCode === 0) {
                $io->newLine();
                $io->success('Tests completed successfully!');

                return self::SUCCESS;
            } else {
                $io->newLine();
                $io->error('Tests failed!');

                return self::FAILURE;
            }
        } catch (Exception $e) {
            $io->error('An error occurred while running tests: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
