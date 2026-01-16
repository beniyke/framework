<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * DownloadCommand downloads a package from GitHub.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Package\Commands;

use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Paths;
use Package\PackageManager;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class DownloadCommand extends Command
{
    private const GITHUB_ORG_URL = "https://github.com/anchor/";

    private PackageManager $packageManager;

    private FileMetaInterface $fileMeta;

    private FileManipulationInterface $fileManipulation;

    public function __construct(
        PackageManager $packageManager,
        FileMetaInterface $fileMeta,
        FileManipulationInterface $fileManipulation
    ) {
        parent::__construct();
        $this->packageManager = $packageManager;
        $this->fileMeta = $fileMeta;
        $this->fileManipulation = $fileManipulation;
    }

    protected function configure(): void
    {
        $this->setName('download')
            ->setAliases(['package:download'])
            ->setDescription('Download a package from GitHub and install it.')
            ->addArgument('package', InputArgument::REQUIRED, 'The name of the package (e.g. Ally)')
            ->addOption('branch', 'b', InputOption::VALUE_OPTIONAL, 'The branch to checkout', 'main')
            ->addOption('install', 'i', InputOption::VALUE_NONE, 'Immediately install the package after downloading');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $packageName = $input->getArgument('package');
        $branch = $input->getOption('branch');

        // Handle casing: "ally" -> "Ally"
        $localName = ucfirst($packageName);
        $url = self::GITHUB_ORG_URL . strtolower($packageName) . ".git";
        $targetDir = Paths::basePath("packages" . DIRECTORY_SEPARATOR . $localName);

        $io->title("Downloading Package: {$localName}");
        $io->text("Source: {$url}");
        $io->text("Destination: {$targetDir}");

        if ($this->fileMeta->isDir($targetDir)) {
            $io->warning("Directory {$targetDir} already exists.");
            if (!$io->confirm("Do you want to overwrite it? (This will delete the existing folder)", false)) {
                $io->text("Download aborted.");

                return Command::SUCCESS;
            }
            $this->fileManipulation->delete($targetDir);
        }

        try {
            $parentDir = dirname($targetDir);
            if (!$this->fileMeta->isDir($parentDir)) {
                $this->fileManipulation->mkdir($parentDir);
            }

            $command = sprintf('git clone -b %s %s %s', escapeshellarg($branch), escapeshellarg($url), escapeshellarg($targetDir));
            $io->text("Running: {$command}");

            exec($command . ' 2>&1', $outputLines, $returnVar);

            if ($returnVar !== 0) {
                if ($branch === 'main') {
                    $io->text("Failed to clone 'main', trying default branch...");
                    $command = sprintf('git clone %s %s', escapeshellarg($url), escapeshellarg($targetDir));
                    exec($command . ' 2>&1', $outputLines, $returnVar);
                }
            }

            if ($returnVar !== 0) {
                $io->error(implode("\n", $outputLines));
                throw new RuntimeException("Git clone failed.");
            }

            $io->success("Package downloaded successfully.");

            if ($input->getOption('install')) {
                $this->installPackage($io, $localName, $targetDir);
            } else {
                $io->text("To install the package, run: php dock package:install {$localName}");
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error("Download failed: " . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function installPackage(SymfonyStyle $io, string $packageName, string $packagePath): void
    {
        try {
            $absolutePath = $packagePath;

            $io->section("Installing {$packageName}...");

            $manifest = $this->packageManager->getManifest($absolutePath);
            $results = $this->packageManager->install($absolutePath, $manifest);

            if ($results['config_count'] > 0) {
                $io->success("Published {$results['config_count']} config file(s).");
            }

            if ($results['migration_count'] > 0) {
                $io->success("Published {$results['migration_count']} migration file(s).");

                if ($results['migrations_run'] > 0) {
                    $io->success("Ran {$results['migrations_run']} migration(s) successfully.");
                }
            }

            if (!empty($manifest)) {
                $io->success("Services registered.");
            }

            foreach ($results['errors'] as $error) {
                $io->warning($error);
            }

            $io->success("Package {$packageName} installed and ready!");
        } catch (Throwable $e) {
            $io->error("Installation validation failed: " . $e->getMessage());
        }
    }
}
