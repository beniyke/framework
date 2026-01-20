<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new Request DTO.
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

class CreateRequestCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('requestname', InputArgument::REQUIRED, 'Name Of The Request DTO to Generate.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Generate The Request DTO to.')
            ->setName('request:create')
            ->setDescription('Creates new Request DTO.')
            ->setHelp('This command allows you to create a new Request DTO...' . PHP_EOL . 'Note: To create a request DTO for a module, first enter the name of the request DTO, add a space, then the name of the module e.g. login account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $requestName = $input->getArgument('requestname');
        $moduleName = $input->getArgument('modulename');

        $io->title('Request DTO Generator');
        $io->note(sprintf(
            'Attempting to create Request DTO "%s"%s.',
            $requestName,
            $moduleName ? ' in module "' . $moduleName . '"' : ' (global)'
        ));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating request DTO file...');
            $build = $generator->request($requestName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $requestName,
                    'Module' => $moduleName ?: '(Global)',
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
            $io->error('Fatal Error during Request DTO Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
