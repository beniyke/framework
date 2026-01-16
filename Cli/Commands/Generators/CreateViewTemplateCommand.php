<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new View Template.
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

class CreateViewTemplateCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('templatename', InputArgument::REQUIRED, 'Name Of The View Template to Create.')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Generate The View Template to.')
            ->addOption('template', null, InputOption::VALUE_REQUIRED, 'Optional base template name to extend or use (e.g., base).')
            ->setName('view:create-template')
            ->setDescription('Creates new View Template.')
            ->setHelp('This command allows you to create a new View Template...' . PHP_EOL . 'Note: To create a view template for a module, first enter the name of the view template, add a space, then the name of the module e.g. login account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $templateName = $input->getArgument('templatename');
        $moduleName = $input->getArgument('modulename');
        $baseTemplate = $input->getOption('template');

        $io->title('View Template Generator');
        $io->note(sprintf('Attempting to create View Template "%s" in module "%s".', $templateName, $moduleName));

        try {

            $generator = Generators::getInstance();
            $io->text('Generating view template file...');
            $build = $generator->template($templateName, $moduleName, $baseTemplate);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Template Name' => $templateName,
                    'Module' => $moduleName,
                    'Base Template' => $baseTemplate ?: 'None specified',
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
            $io->error('Fatal Error during View Template Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
