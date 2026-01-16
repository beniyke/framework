<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * UninstallCommand uninstalls a package.
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

class UninstallCommand extends Command
{
    private PackageManager $packageManager;

    public function __construct(PackageManager $packageManager)
    {
        parent::__construct();
        $this->packageManager = $packageManager;
    }

    protected function configure(): void
    {
        $this->setName('package:uninstall')
            ->setDescription('Uninstall a package (rollback migrations, remove files, unregister services).')
            ->addArgument('package', InputArgument::REQUIRED, 'The name of the package (e.g. Tenancy)')
            ->addOption('system', null, InputOption::VALUE_NONE, 'Install from System directory')
            ->addOption('packages', null, InputOption::VALUE_NONE, 'Install from packages directory')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation checks');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $package = $input->getArgument('package');
        $fromSystem = $input->getOption('system');
        $fromPackages = $input->getOption('packages');
        $skipConfirmation = $input->getOption('yes');

        if (($fromSystem && $fromPackages) || (!$fromSystem && !$fromPackages)) {
            $io->error('Please specify exactly one source: --system or --packages');

            return Command::FAILURE;
        }

        $io->title("Uninstalling Package: {$package}");

        if (!$skipConfirmation && !$io->confirm("Are you sure you want to uninstall {$package}? This will rollback migrations and delete data.", false)) {
            $io->text('Uninstall cancelled.');

            return Command::SUCCESS;
        }

        try {
            $packagePath = $this->packageManager->resolvePackagePath($package, (bool) $fromSystem);
            $io->text("Resolved path: {$packagePath}");

            $manifest = $this->packageManager->getManifest($packagePath);

            $this->packageManager->uninstall($packagePath, $manifest);

            $io->success("Package {$package} uninstalled successfully.");

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error("Uninstall failed: " . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
