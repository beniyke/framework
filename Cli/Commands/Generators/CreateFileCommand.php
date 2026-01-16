<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new file.
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

class CreateFileCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('filename', InputArgument::REQUIRED, 'Name of the file to be created.')
            ->setName('file:create')
            ->setDescription('Creates new file.')
            ->setHelp('This command allows you to create new file...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fileName = $input->getArgument('filename');

        $io->title('File Generator');
        $io->note(sprintf('Attempting to create file: "%s" under the "App" path.', ucfirst($fileName)));

        try {
            $generator = Generators::getInstance();

            $io->text('Creating file...');

            $build = $generator->path('App')
                ->file(ucfirst($fileName));

            if ($build['status']) {
                $io->success($build['message']);

                if (isset($build['path'])) {
                    $io->definitionList([
                        'File Name' => ucfirst($fileName),
                        'Created At' => $build['path'],
                    ]);
                }
            } else {
                $io->error('Creation Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during File Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
