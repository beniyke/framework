<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 *  UpdateCommand handles the unified updating of the framework core.
 *  It detects whether the installation is Managed (Composer) or Standalone (Hydrated)
 *  and executes the appropriate update mechanism.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Package\Commands;

use Core\Support\Adapters\Interfaces\OSCheckerInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Paths;
use Package\Services\HydrationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Throwable;

class UpdateCommand extends Command
{
    private const GITHUB_TAG_URL = "https://github.com/beniyke/anchor/archive/refs/tags/%s.zip";

    private HydrationService $hydrationService;

    private FileMetaInterface $fileMeta;

    private OSCheckerInterface $osChecker;

    public function __construct(HydrationService $hydrationService, FileMetaInterface $fileMeta, OSCheckerInterface $osChecker)
    {
        parent::__construct();
        $this->hydrationService = $hydrationService;
        $this->fileMeta = $fileMeta;
        $this->osChecker = $osChecker;
    }

    protected function configure(): void
    {
        $this->setName('anchor:update')
            ->setAliases(['framework:update'])
            ->setDescription('Intelligently updates the framework core based on the installation mode.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force update and overwrite files (Standalone mode only)')
            ->addOption('tag', 't', InputOption::VALUE_OPTIONAL, 'Specific version tag to pull (Standalone mode only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Framework Update Engine');

        $isManaged = $this->isManagedMode();
        $modeName = $isManaged ? 'Managed (Composer)' : 'Standalone (Portable)';

        $io->note("Detected Installation Mode: {$modeName}");

        if ($isManaged) {
            return $this->handleManagedUpdate($io);
        }

        return $this->handleStandaloneUpdate($io, $input);
    }

    private function isManagedMode(): bool
    {
        $vendorPath = Paths::basePath('vendor/beniyke/framework');

        return $this->fileMeta->isDir($vendorPath) && $this->fileMeta->isFile(Paths::basePath('composer.json'));
    }

    protected function handleManagedUpdate(SymfonyStyle $io): int
    {
        $composer = $this->findComposerBinary();

        if (!$composer) {
            $io->error("Composer binary not found. Please ensure Composer is installed and globally accessible.");

            return Command::FAILURE;
        }

        $io->text("Invoking: <info>{$composer} update beniyke/framework</info>");

        try {
            $process = Process::fromShellCommandline("{$composer} update beniyke/framework");
            $process->setTimeout(null);
            $process->setTty(Process::isTtySupported());

            $process->run(function ($type, $buffer) {
                echo $buffer;
            });

            if ($process->isSuccessful()) {
                $io->success("Framework updated successfully via Composer.");

                return Command::SUCCESS;
            }

            $io->error("Composer update failed with exit code: " . $process->getExitCode());

            return Command::FAILURE;
        } catch (Throwable $e) {
            $io->error("An error occurred while running Composer: " . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function findComposerBinary(): ?string
    {
        if ($this->fileMeta->isFile(Paths::basePath('composer.phar'))) {
            return PHP_BINARY . ' composer.phar';
        }

        $checkCmd = $this->osChecker->isWindows() ? 'where composer' : 'command -v composer';
        $process = Process::fromShellCommandline($checkCmd);

        try {
            $process->run();

            if ($process->isSuccessful() && !empty(trim($process->getOutput()))) {
                return 'composer';
            }
        } catch (Throwable) {
            // Fallback or fail silently if process execution fails
        }

        return null;
    }

    private function handleStandaloneUpdate(SymfonyStyle $io, InputInterface $input): int
    {
        try {
            $io->text('Checking latest release on GitHub...');
            $release = $this->hydrationService->getLatestRelease();
            $tagName = $input->getOption('tag') ?? $release['tag_name'];
            $zipUrl = $release['zipball_url'];

            if ($input->getOption('tag')) {
                $zipUrl = sprintf(self::GITHUB_TAG_URL, $tagName);
            }

            $io->note("Target Version: {$tagName}");

            if (!$input->getOption('force')) {
                if (!$io->confirm("This will overwrite your framework core files (System and libs). Continue?", true)) {
                    $io->text('Update cancelled.');

                    return Command::SUCCESS;
                }
            }

            $tempZip = Paths::storagePath('temp_framework_update.zip');

            $io->text('Downloading core artifacts...');
            $this->hydrationService->downloadZip($zipUrl, $tempZip);

            $io->text('Applying updates...');
            $results = $this->hydrationService->extract($tempZip, Paths::basePath());

            $this->hydrationService->cleanup($tempZip);

            if (empty($results['errors'])) {
                $io->success("Framework core updated to {$tagName} successfully!");

                return Command::SUCCESS;
            }

            $io->warning("Update completed with some errors:");
            $io->listing($results['errors']);

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error("Standalone update failed: " . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
