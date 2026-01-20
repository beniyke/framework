<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new View Modal.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Generators;

use Cli\Build\Generators;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateViewModalCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('modalname', InputArgument::REQUIRED, 'Name Of The View Modal to Create.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Generate The View Modal to.')
            ->addOption('endpoint', null, InputOption::VALUE_REQUIRED, 'Optional AJAX endpoint for the modal form/data.')
            ->setName('view:create-modal')
            ->setDescription('Creates new View Modal.')
            ->setHelp('This command allows you to create a new View Modal...' . PHP_EOL . 'Note: To create a view modal for a module, first enter the name of the view modal, add a space, then the name of the module e.g. login account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $modalName = $input->getArgument('modalname');
        $moduleName = $input->getArgument('modulename');
        $endpoint = $input->getOption('endpoint');

        $io->title('View Modal Generator');
        $io->note(sprintf(
            'Attempting to create View Modal "%s"%s.',
            $modalName,
            $moduleName ? ' in module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating view modal file...');

            $build = $generator->modal($modalName, $moduleName, $endpoint);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Modal Name' => $modalName,
                    'Module' => $moduleName ?: '(Global)',
                ];

                if ($endpoint) {
                    $details['Endpoint'] = $endpoint;
                }

                if (isset($build['path'])) {
                    $details['File Path'] = $build['path'];
                }

                $io->definitionList($details);
            } else {
                $io->error('Generation Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during View Modal Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
