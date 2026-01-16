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

class SyncVersionCommand extends Command
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
        $this->setName('version:sync')
            ->setDescription('Syncs version from version.txt to System/Core/App.php constant');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Syncing Framework Version');

        $versionFile = $this->paths->basePath('version.txt');
        $appFile = $this->paths->systemPath('Core' . DIRECTORY_SEPARATOR . 'App.php');

        if (!$this->fileMeta->exists($versionFile)) {
            $io->error("version.txt not found at $versionFile");

            return Command::FAILURE;
        }

        if (!$this->fileMeta->exists($appFile)) {
            $io->error("App.php not found at $appFile");

            return Command::FAILURE;
        }

        $version = trim($this->fileReadWrite->get($versionFile));
        $content = $this->fileReadWrite->get($appFile);

        $pattern = "/public const VERSION = '(.*?)';/";
        $replacement = "public const VERSION = '$version';";

        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, $replacement, $content);
            if ($this->fileReadWrite->put($appFile, $newContent)) {
                $io->success("Updated App::VERSION to $version");

                return Command::SUCCESS;
            }
        }

        $io->error("Could not find VERSION constant in App.php");

        return Command::FAILURE;
    }
}
