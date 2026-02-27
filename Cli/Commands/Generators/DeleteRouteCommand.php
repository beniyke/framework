<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete manual routing for a module.
 *
 * @author BenIyke <beniyke34@gmail.com>
 */

namespace Cli\Commands\Generators;

use Cli\Build\Generators;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteRouteCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('module', InputArgument::REQUIRED, 'Name of the module to delete routing for.')
            ->setName('route:delete')
            ->setDescription('Deletes manual routing map for a module.')
            ->setHelp('This command removes the Route/map.php file from the specified module.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $module = $input->getArgument('module');

        $io->title('Route Deletion');
        $io->note(sprintf('Attempting to delete manual routing for module "%s".', $module));

        try {
            $generator = Generators::getInstance();
            $build = $generator->deleteRouteMap($module);

            if ($build['status']) {
                $io->success($build['message']);
            } else {
                $io->error('Deletion Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Route Deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
