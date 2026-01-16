<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to format code by removing unnecessary comments and running Pint.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Code;

use Formatter\CodeFormatter;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Throwable;

class FormatCommand extends Command
{
    private PathResolverInterface $paths;

    private FileReadWriteInterface $fileReadWrite;

    private ?ProgressBar $progressBar = null;

    public function __construct(PathResolverInterface $paths, FileReadWriteInterface $fileReadWrite)
    {
        parent::__construct();
        $this->paths = $paths;
        $this->fileReadWrite = $fileReadWrite;
    }

    protected function configure(): void
    {
        $this
            ->setName('format')
            ->setDescription('Format code by removing unnecessary comments and running Pint.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without modifying files')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Check for formatting issues without modifying (fails if issues found)')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Target directory (default: App,System)', null)
            ->addOption('backup', null, InputOption::VALUE_NONE, 'Create .bak files before modifying')
            ->addOption('max-size', null, InputOption::VALUE_REQUIRED, 'Max file size in MB (default: 5)', 5)
            ->addOption('skip-pint', null, InputOption::VALUE_NONE, 'Skip running Pint after cleanup')
            ->addOption('pint-dirty', null, InputOption::VALUE_NONE, 'Only format files changed in git (faster)')
            ->addOption('pint-timeout', null, InputOption::VALUE_REQUIRED, 'Pint timeout in seconds (default: 600)', 600)
            ->addOption('pint-per-path', null, InputOption::VALUE_NONE, 'Run Pint separately per directory (parallel-like)')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Directories to exclude (comma-separated)', [])
            ->addOption('show-skipped', null, InputOption::VALUE_NONE, 'Show all skipped files with reasons');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Load configuration file first
        $config = $this->loadConfig();

        // CLI options override config file
        $dryRun = (bool) $input->getOption('dry-run');
        $checkOnly = (bool) $input->getOption('check');

        if ($checkOnly) {
            $dryRun = true;
        }
        $targetPath = $input->getOption('path') ?? $config['paths'] ?? null;
        $createBackup = $input->getOption('backup') ? true : ($config['backup'] ?? false);
        $maxSize = (int) ($input->getOption('max-size') ?: ($config['maxFileSize'] ?? 5));
        $skipPint = $input->getOption('skip-pint') ? true : ($config['skipPint'] ?? false);
        $customExcludes = !empty($input->getOption('exclude')) ? $input->getOption('exclude') : ($config['exclude'] ?? []);
        $showSkipped = (bool) $input->getOption('show-skipped');

        $io->title('Code Formatter');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No files will be modified');
        }

        if ($createBackup) {
            $io->note('Backup mode enabled - .bak files will be created');
        }

        // Determine paths to process
        $paths = is_array($targetPath) ? $this->resolveMultiplePaths($targetPath) : $this->resolvePaths($targetPath);

        if (empty($paths)) {
            $io->error('No valid paths found to process.');

            return self::FAILURE;
        }

        // Prepare exclusions
        $excludes = $this->prepareExclusions($customExcludes);
        if (!empty($excludes)) {
            $io->note('Excluding: ' . implode(', ', $excludes));
        }

        // Build formatter config from file + CLI options
        $formatterConfig = [
            'max_file_size_mb' => $maxSize,
            'create_backup' => $createBackup,
            'exclude_patterns' => $excludes,
            // Feature toggles from .formatter.json (or defaults)
            'remove_empty_comments' => $config['remove_empty_comments'] ?? true,
            'remove_todo_markers' => $config['remove_todo_markers'] ?? true,
            'remove_numbered_steps' => $config['remove_numbered_steps'] ?? true,
            'remove_obvious_actions' => $config['remove_obvious_actions'] ?? true,
            'remove_commented_code' => $config['remove_commented_code'] ?? true,
            'remove_redundant_docblocks' => $config['remove_redundant_docblocks'] ?? true,
            'clean_empty_lines' => $config['clean_empty_lines'] ?? true,
            'aggressive_mode' => $config['aggressive_mode'] ?? false,
        ];

        $formatter = new CodeFormatter($this->fileReadWrite, $formatterConfig);

        // Setup progress callback
        $formatter->setProgressCallback(function (array $progress) use ($io, $dryRun, $showSkipped) {
            if ($this->progressBar !== null) {
                $this->progressBar->advance();
            } elseif ($progress['action'] === 'processed' && !$dryRun) {
                $io->text("  <info>✓</info> {$progress['file']} ({$progress['detail']} changes)");
            } elseif ($showSkipped && $progress['action'] === 'skipped_excluded') {
                $io->text("  <fg=gray>⊘</> {$progress['file']} (excluded directory)");
            } elseif ($progress['action'] === 'skipped_size') {
                $io->text("  <fg=yellow>⊘</> {$progress['file']} (too large: " . round($progress['detail'] / 1024 / 1024, 2) . " MB)");
            } elseif ($showSkipped && $progress['action'] === 'skipped_permission') {
                $io->text("  <fg=red>⊘</> {$progress['file']} (permission denied)");
            }
        });

        $io->section('Step 1: Cleaning Comments');
        $totalResults = [
            'files_processed' => 0,
            'files_skipped' => 0,
            'total_changes' => 0,
            'stats' => [
                'docblocks_removed' => 0,
                'inline_comments_removed' => 0,
                'numbered_steps_removed' => 0,
                'obvious_actions_removed' => 0,
                'empty_lines_cleaned' => 0,
            ],
            'errors' => [],
        ];

        foreach ($paths as $path) {
            $io->text("Processing directory: <info>{$path}</info>");
            $result = $formatter->formatDirectory($path, $dryRun);

            $totalResults['files_processed'] += $result['files_processed'];
            $totalResults['files_skipped'] += $result['files_skipped'];
            $totalResults['total_changes'] += $result['total_changes'];
            $totalResults['errors'] = array_merge($totalResults['errors'], $result['errors']);

            // Merge stats
            if (!empty($result['stats'])) {
                foreach ($result['stats'] as $key => $value) {
                    $totalResults['stats'][$key] += $value;
                }
            }
        }

        // Display summary
        $io->newLine();
        $io->text("Processed: <info>{$totalResults['files_processed']}</info> files");
        $io->text("Changes made: <info>{$totalResults['total_changes']}</info> items removed");

        // Display detailed stats if available
        if (!empty($totalResults['stats'])) {
            $stats = $totalResults['stats'];
            $io->newLine();
            $io->text('<comment>Breakdown:</comment>');
            if ($stats['docblocks_removed'] > 0) {
                $io->text("  • Redundant docblocks: <info>{$stats['docblocks_removed']}</info>");
            }
            if ($stats['inline_comments_removed'] > 0) {
                $io->text("  • Inline comments: <info>{$stats['inline_comments_removed']}</info>");
            }
            if ($stats['numbered_steps_removed'] > 0) {
                $io->text("  • Numbered steps: <info>{$stats['numbered_steps_removed']}</info>");
            }
            if ($stats['obvious_actions_removed'] > 0) {
                $io->text("  • Obvious action comments: <info>{$stats['obvious_actions_removed']}</info>");
            }
            if ($stats['empty_lines_cleaned'] > 0) {
                $io->text("  • Empty lines cleaned: <info>{$stats['empty_lines_cleaned']}</info>");
            }
        }

        if ($totalResults['files_skipped'] > 0) {
            $io->text("Skipped: <fg=yellow>{$totalResults['files_skipped']}</> files (too large, symlinks, or permission issues)");
        }

        if (!empty($totalResults['errors'])) {
            $errorCount = count($totalResults['errors']);
            $io->warning("Encountered {$errorCount} errors:");
            foreach (array_slice($totalResults['errors'], 0, 5) as $error) {
                $io->text("  <fg=red>•</> {$error}");
            }
            if ($errorCount > 5) {
                $io->text("  ... and " . ($errorCount - 5) . " more");
            }
        }

        if ($dryRun) {
            $io->warning('DRY RUN - No files were modified. Run without --dry-run to apply changes.');

            return self::SUCCESS;
        }

        if (!$skipPint) {
            $io->section('Step 2: Running Pint');

            $pintDirty = (bool) $input->getOption('pint-dirty');
            $pintTimeout = (int) $input->getOption('pint-timeout');
            $pintPerPath = (bool) $input->getOption('pint-per-path');

            if ($pintPerPath) {
                foreach ($paths as $path) {
                    $io->text("Running Pint on: <info>{$path}</info>");
                    $this->runPint($io, $path, $pintDirty, $pintTimeout);
                }
            } else {
                $this->runPint($io, null, $pintDirty, $pintTimeout);
            }
        } else {
            $io->text('Skipped Pint (--skip-pint option)');
        }

        $io->success('Code formatting completed!');

        if ($checkOnly && $totalResults['total_changes'] > 0) {
            $io->error('Unnecessary comments found! Please run "php dock format" to clean them up.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolvePaths(?string $customPath): array
    {
        if ($customPath !== null) {
            $resolvedPath = $this->paths->basePath($customPath);

            return is_dir($resolvedPath) ? [$resolvedPath] : [];
        }

        // Default paths
        return [
            $this->paths->appPath(),
            $this->paths->systemPath(),
        ];
    }

    private function prepareExclusions(array $customExcludes): array
    {
        // If custom exclusions provided (from config or CLI), use them directly
        // This gives full control to the config file
        if (!empty($customExcludes)) {
            return array_unique($customExcludes);
        }

        // Only use defaults if no config file exists or is empty
        return [
            'vendor',
            'node_modules',
            'storage',
            '.git',
            '.idea',
            'cache',
        ];
    }

    private function loadConfig(): array
    {
        $configPath = $this->paths->basePath('.formatter.json');

        if (!file_exists($configPath)) {
            return [];
        }

        try {
            $content = file_get_contents($configPath);
            $config = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }

            return $config ?? [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function resolveMultiplePaths(array $paths): array
    {
        $resolved = [];
        foreach ($paths as $path) {
            $resolvedPath = $this->paths->basePath($path);
            if (is_dir($resolvedPath)) {
                $resolved[] = $resolvedPath;
            }
        }

        return $resolved;
    }

    private function runPint(SymfonyStyle $io, ?string $path = null, bool $dirty = false, int $timeout = 600): void
    {
        $pintPath = $this->paths->basePath('vendor/bin/pint');

        if (!file_exists($pintPath)) {
            $io->warning('Pint not found. Skipping code formatting.');

            return;
        }

        $command = [$pintPath];

        if ($path !== null) {
            $command[] = $path;
        }

        if ($dirty) {
            $command[] = '--dirty';
            $io->text('<comment>Using --dirty mode (only git-changed files)</comment>');
        }

        $process = new Process($command);
        $process->setTimeout($timeout);

        $io->text("<fg=gray>Timeout: {$timeout}s</>");

        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $exitCode = $process->getExitCode();
            $io->newLine();

            if ($exitCode === null) {
                $io->error('Pint timed out. Try: --pint-timeout=900 or --pint-dirty or --pint-per-path');
            } else {
                $io->error('Pint failed with exit code: ' . $exitCode);
            }
        }
    }
}
