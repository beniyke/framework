<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * InstallCommand installs a package.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Package\Commands;

use Package\PackageManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class InstallCommand extends Command
{
    private PackageManager $packageManager;

    public function __construct(PackageManager $packageManager)
    {
        parent::__construct();
        $this->packageManager = $packageManager;
    }

    protected function configure(): void
    {
        $this->setName('package:install')
            ->setDescription('Install a package (publish config, migrations, register services).')
            ->addArgument('package', InputArgument::REQUIRED, 'The name of the package (e.g. Tenancy)')
            ->addOption('system', null, InputOption::VALUE_NONE, 'Install from System directory')
            ->addOption('packages', null, InputOption::VALUE_NONE, 'Install from packages directory')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force installation (bypass confirmation)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $package = $input->getArgument('package');
        $fromSystem = $input->getOption('system');
        $fromPackages = $input->getOption('packages');

        if (($fromSystem && $fromPackages) || (!$fromSystem && !$fromPackages)) {
            $io->error('Please specify exactly one source: --system or --packages');

            return Command::FAILURE;
        }

        $io->title("Installing Package: {$package}");

        try {
            $packagePath = $this->packageManager->resolvePackagePath($package, (bool) $fromSystem);
            $io->text("Resolved path: {$packagePath}");

            $manifest = $this->packageManager->getManifest($packagePath);
            $status = $this->packageManager->checkStatus($packagePath, $manifest);

            if ($status === PackageManager::STATUS_INSTALLED) {
                $io->success("Package {$package} is already installed.");

                return Command::SUCCESS;
            }

            if ($status === PackageManager::STATUS_MISSING_FILES) {
                $io->warning("Package {$package} is in a partial state (some files missing).");

                $shouldReinstall = $input->getOption('force') || $io->confirm("Do you want to repair/re-install it?", true);

                if ($shouldReinstall) {
                    $io->section("Uninstalling previous version...");
                    $this->packageManager->uninstall($packagePath, $manifest);
                } else {
                    $io->text("Installation abort.");

                    return Command::SUCCESS;
                }
            }

            $results = $this->packageManager->install($packagePath, $manifest);

            if ($results['config_count'] > 0) {
                $io->success("Published {$results['config_count']} config file(s).");
            }

            if ($results['migration_count'] > 0) {
                $io->success("Published {$results['migration_count']} migration file(s).");

                if ($results['migrations_run'] > 0) {
                    $io->success("Ran {$results['migrations_run']} migration(s) successfully.");
                } elseif (empty($results['errors'])) {
                    $io->comment("No pending migrations to run.");
                }
            }

            if (!empty($manifest)) {
                $io->success("Services registered from setup.php");
            }

            foreach ($results['errors'] as $error) {
                $io->warning($error);
            }

            $io->success("Package {$package} installed successfully!");

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error("Installation failed: " . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
