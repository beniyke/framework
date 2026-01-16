<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new directory.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Generators;

use Cli\Build\Generators;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateDirectoryCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('directoryname', InputArgument::REQUIRED, 'Name of the directory to be created.')
            ->setName('directory:create')
            ->setDescription('Creates new Directory.')
            ->setHelp('This command allows you to create new directory...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directoryName = $input->getArgument('directoryname');

        $io->title('Directory Generator');
        $io->note(sprintf('Attempting to create directory: "%s" under "App".', ucfirst($directoryName)));

        try {

            $generator = Generators::getInstance();
            $io->text('Creating directory structure...');
            $build = $generator->path('App')
                ->directory(ucfirst($directoryName));

            if ($build['status']) {
                $io->success($build['message']);

                if (isset($build['path'])) {
                    $io->definitionList([
                        'Directory Name' => ucfirst($directoryName),
                        'Created At' => $build['path'],
                    ]);
                }
            } else {
                $io->error('Creation Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Directory Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
