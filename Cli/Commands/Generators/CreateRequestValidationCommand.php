<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new Request Validation.
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

class CreateRequestValidationCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('validation_name', InputArgument::REQUIRED, 'Name Of The Request Validation to Generate.')
            ->addArgument('modulename', InputArgument::REQUIRED, 'Name Of The Module to Generate The Request Validation to.')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'The type of validation (form or api)')
            ->setName('validation:create')
            ->setDescription('Creates new Request Validation.')
            ->setHelp('This command allows you to create a new Request Validation...' . PHP_EOL . 'Note: To create a Request Validation for a module, first enter the name of the Request Validation, add a space, then the name of the module, and specify the type using --type=form or --type=api' . PHP_EOL . 'Example: php dock validation:create Login Auth --type=form');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $validationName = $input->getArgument('validation_name');
        $moduleName = $input->getArgument('modulename');
        $validationType = $input->getOption('type');

        // Validate that --type option is provided
        if (! $validationType) {
            $io->error('The --type option is required. Please specify either --type=form or --type=api');

            return self::FAILURE;
        }

        // Validate that type is either 'form' or 'api'
        $allowedTypes = ['form', 'api'];
        if (! in_array(strtolower($validationType), $allowedTypes)) {
            $io->error(sprintf('Invalid type "%s". Allowed types are: %s', $validationType, implode(', ', $allowedTypes)));

            return self::FAILURE;
        }

        $io->title('Request Validation Generator');
        $io->note(sprintf('Attempting to create %s Request Validation Class "%s" in module "%s".', ucfirst(strtolower($validationType)), $validationName, $moduleName));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating Request Validation file...');
            $build = $generator->requestValidation($validationName, $moduleName, strtolower($validationType));

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $validationName,
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
            $io->error('Fatal Error during Request Validation Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
