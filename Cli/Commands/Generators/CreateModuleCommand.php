<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new module.
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

class CreateModuleCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Generate.')
            ->addOption('api', null, InputOption::VALUE_NONE, 'Generate a minimal API-focused module structure (without Views).')
            ->setName('module:create')
            ->setDescription('Creates new Module.')
            ->setHelp('This command allows you to create new Module...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $moduleName = ucfirst($input->getArgument('modulename'));
        $isApi = $input->getOption('api');

        $sub_directories = 'Actions,Controllers,Models,Services,Views,Views/Templates,Views/Templates/modals,Views/Templates/inc';

        if ($isApi) {
            $sub_directories = 'Actions,Controllers,Models,Resources,Services';
        }

        $io->title('Module Generator');
        $io->note(sprintf('Attempting to create %s Module "%s" under "App/src".', $isApi ? 'API' : 'Standard', $moduleName));

        try {
            $generator = Generators::getInstance();
            $io->text('Creating base directory and subdirectories...');

            $build = $generator->path('App/src')
                ->directory($moduleName, $sub_directories);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Module Name' => $moduleName,
                    'Type' => $isApi ? 'API' : 'Standard (with Views)',
                    'Created At' => isset($build['path']) ? $build['path'] : 'N/A',
                ];

                $io->definitionList($details);
                $io->comment('Subdirectories Created:');
                $io->listing(explode(',', $sub_directories));
            } else {
                $io->error('Creation Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Module Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
