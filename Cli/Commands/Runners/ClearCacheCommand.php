<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to clear the application cache.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Runners;

use Exception;
use Helpers\File\FileSystem;
use Helpers\File\Paths;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClearCacheCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('cachedirectory', InputArgument::OPTIONAL, 'The cache directory or key namespace to flush.')
            ->addArgument('cachefile', InputArgument::OPTIONAL, 'A specific cache file/key to delete within the directory.')
            ->setName('cache:flush')
            ->setDescription('Clears all or a specific key within a cache namespace.')
            ->setHelp('This command clears app cache, either a whole directory/namespace or a single file/key.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cacheDirectory = $input->getArgument('cachedirectory');
        $cacheFile = $input->getArgument('cachefile');
        $io->title('Application Cache Flusher');

        if (empty($cacheDirectory)) {
            $io->section('Attempting to clear all application cache.');
            $io->text('Performing cache operation...');
            $cachePath = Paths::storagePath('cache');

            if (FileSystem::delete($cachePath, true)) {
                $io->success('Successfully cleared all application cache.');

                return self::SUCCESS;
            }
            $io->error('Failed to clear all cache. Please check file permissions.');

            return self::FAILURE;
        }
        $operation = $cacheFile
            ? sprintf('Attempting to delete specific key "%s" from namespace "%s".', $cacheFile, $cacheDirectory)
            : sprintf('Attempting to clear entire cache namespace: "%s".', $cacheDirectory);
        $io->section($operation);
        try {
            $io->text('Performing cache operation...');
            $clear = empty($cacheFile)
                ? cache($cacheDirectory)->clear()
                : cache($cacheDirectory)->delete($cacheFile);
            if ($clear) {
                $io->success(
                    $cacheFile
                        ? "Successfully deleted cache key: {$cacheFile}."
                        : "Successfully cleared cache namespace: {$cacheDirectory}."
                );
            } else {
                $io->warning('Cache could not be cleared or the key was not found. The cache backend returned a negative status.');
                if ($cacheFile) {
                    $io->note(sprintf('Key checked: %s in namespace %s.', $cacheFile, $cacheDirectory));
                }
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('A fatal error occurred during cache operation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
