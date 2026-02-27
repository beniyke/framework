<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to setup manual routing for a module.
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

class CreateRouteSetupCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('module', InputArgument::REQUIRED, 'Name of the module to setup routing for.')
            ->setName('route:setup')
            ->setDescription('Sets up manual routing map for a module.')
            ->setHelp('This command creates a Route/map.php file in the specified module.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $module = $input->getArgument('module');

        $io->title('Route Setup');
        $io->note(sprintf('Attempting to setup manual routing for module "%s".', $module));

        try {
            $generator = Generators::getInstance();
            $build = $generator->routeMap($module);

            if ($build['status']) {
                $io->success($build['message']);
                if (isset($build['path'])) {
                    $io->text('Route file created at: ' . $build['path']);
                }
            } else {
                $io->error('Setup Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Route Setup: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
