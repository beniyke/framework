<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new Service.
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

class CreateServiceCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('servicename', InputArgument::REQUIRED, 'Name Of The Service to Generate.')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Generate The Service to.')
            ->setName('service:create')
            ->setDescription('Creates new Service.')
            ->setHelp('This command allows you to create a new Service...' . PHP_EOL . 'Note: To create a Service for a module, first enter the name of the Service, add a space, then the name of the module e.g. login account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $serviceName = $input->getArgument('servicename');
        $moduleName = $input->getArgument('modulename');

        $io->title('Service Class Generator');
        $io->note(sprintf('Attempting to create Service Class "%s" in module "%s".', $serviceName, $moduleName));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating service class file...');
            $build = $generator->service($serviceName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $serviceName,
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
            $io->error('Fatal Error during Service Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
