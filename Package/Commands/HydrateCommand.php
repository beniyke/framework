<?php

declare(strict_types=1);

namespace Package\Commands;

use Helpers\File\Paths;
use Package\Services\HydrationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * HydrateCommand handles the self-provisioning of the framework's core files.
 */
class HydrateCommand extends Command
{
    private HydrationService $hydrationService;

    public function __construct(HydrationService $hydrationService)
    {
        parent::__construct();
        $this->hydrationService = $hydrationService;
    }

    protected function configure(): void
    {
        $this->setName('framework:hydrate')
            ->setDescription('Downloads and installs/updates the framework core files (System and libs).')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files without asking')
            ->addOption('tag', 't', InputOption::VALUE_OPTIONAL, 'Specific version tag to download');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('⚓ Framework Hydrator');

        try {
            $io->text('Checking latest release on GitHub...');
            $release = $this->hydrationService->getLatestRelease();
            $tagName = $input->getOption('tag') ?? $release['tag_name'];
            $zipUrl = $release['zipball_url'];

            if ($input->getOption('tag')) {
                // If specific tag, we need to update the zipUrl (simplified for now assuming latest)
                $zipUrl = "https://github.com/beniyke/anchor/archive/refs/tags/{$tagName}.zip";
            }

            $io->note("Target Version: {$tagName}");

            if (!$input->getOption('force')) {
                if (!$io->confirm("This will download and potentially overwrite your System and libs directories. Continue?", true)) {
                    $io->text('Hydration cancelled.');

                    return Command::SUCCESS;
                }
            }

            $tempZip = Paths::storagePath('temp_framework.zip');

            $io->text('Downloading framework artifacts...');
            $this->hydrationService->downloadZip($zipUrl, $tempZip);

            $io->text('Extracting core files...');
            $results = $this->hydrationService->extract($tempZip, Paths::basePath());

            $this->hydrationService->cleanup($tempZip);

            if (empty($results['errors'])) {
                $io->success("Framework hydrated successfully! {$results['count']} files processed.");
                $io->note("You are now running Anchor Framework version {$tagName}");
            } else {
                $io->warning("Hydration completed with some errors:");
                $io->listing($results['errors']);
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error("Hydration failed: " . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
