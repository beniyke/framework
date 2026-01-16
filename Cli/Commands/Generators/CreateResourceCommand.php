<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new API Resource.
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

class CreateResourceCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('resourcename', InputArgument::REQUIRED, 'Name Of The Resource to Generate.')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Generate The Resource to.')
            ->setName('resource:create')
            ->setDescription('Creates new Resource.')
            ->setHelp('This command allows you to create a new Resource...' . PHP_EOL . 'Note: To create a Resource for a module, first enter the name of the Resource, add a space, then the name of the module e.g. login account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $resourceName = $input->getArgument('resourcename');
        $moduleName = $input->getArgument('modulename');

        $io->title('API Resource Generator');
        $io->note(sprintf('Attempting to create Resource Class "%s" in module "%s".', $resourceName, $moduleName));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating resource file...');

            $build = $generator->resource($resourceName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $resourceName,
                    'Module' => $moduleName,
                ];

                if (isset($build['path'])) {
                    $details['File Path'] = $build['path'];
                }

                $io->definitionList($details);
            } else {
                $io->error('Generation Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Resource Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
