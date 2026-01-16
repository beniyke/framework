<?php

declare(strict_types=1);

namespace Cli\Commands\Runners;

use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncDocsCommand extends Command
{
    private PathResolverInterface $paths;

    private FileMetaInterface $fileMeta;

    private FileReadWriteInterface $fileReadWrite;

    public function __construct(PathResolverInterface $paths, FileMetaInterface $fileMeta, FileReadWriteInterface $fileReadWrite)
    {
        parent::__construct();
        $this->paths = $paths;
        $this->fileMeta = $fileMeta;
        $this->fileReadWrite = $fileReadWrite;
    }

    protected function configure(): void
    {
        $this->setName('docs:sync')
            ->setDescription('Syncs documentation from docs/ to packages/{Package}/README.md');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Syncing Documentation to Packages');

        $packagesPath = $this->paths->basePath('packages');
        $docsPath = $this->paths->basePath('docs');

        if (!$this->fileMeta->isDir($packagesPath)) {
            $io->error("Packages directory not found at $packagesPath");

            return Command::FAILURE;
        }

        $packages = $this->discoverSubdirectories($packagesPath);
        $synced = 0;

        foreach ($packages as $package) {
            $lowerPackage = strtolower($package);
            $docFile = $docsPath . DIRECTORY_SEPARATOR . $lowerPackage . '.md';

            if ($this->fileMeta->exists($docFile)) {
                $target = $packagesPath . DIRECTORY_SEPARATOR . $package . DIRECTORY_SEPARATOR . 'README.md';
                $content = $this->fileReadWrite->get($docFile);

                // Add header to indicate source
                $header = "<!-- This file is auto-generated from docs/$lowerPackage.md -->\n\n";

                if ($this->fileReadWrite->put($target, $header . $content)) {
                    $io->text("<info>✓</info> Synced $lowerPackage.md to $package/README.md");
                    $synced++;
                } else {
                    $io->error("Failed to write to $target");
                }
            }
        }

        $io->success("Synced docs for $synced packages.");

        return Command::SUCCESS;
    }

    private function discoverSubdirectories(string $path): array
    {
        if (!$this->fileMeta->isDir($path)) {
            return [];
        }

        return array_filter(
            scandir($path),
            fn ($item) => $item !== '.' && $item !== '..' && $this->fileMeta->isDir($path . DIRECTORY_SEPARATOR . $item)
        );
    }
}
