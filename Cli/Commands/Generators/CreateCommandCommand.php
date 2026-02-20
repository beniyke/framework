<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new custom command.
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

class CreateCommandCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('commandname', InputArgument::REQUIRED, 'Name of the command class to generate')
            ->addArgument('module', InputArgument::OPTIONAL, 'The module name if creating in App/src/{Module}')
            ->setName('command:create')
            ->setDescription('Creates new command class.')
            ->setHelp('This command allows you to create a new custom command class file in App/Commands or App/src/{Module}/Commands.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $commandName = $input->getArgument('commandname');
        $module = $input->getArgument('module');

        $io->title('Custom Command Generator');
        $io->note(sprintf(
            'Attempting to create command class: "%s"%s.',
            $commandName,
            $module ? " in module \"{$module}\"" : " in App"
        ));

        try {
            $generator = Generators::getInstance();

            $io->text('Generating command file...');

            $build = $generator->command($commandName, $module);

            if ($build['status']) {
                $io->success($build['message']);

                if (isset($build['path'])) {
                    $io->definitionList([
                        'Class Name' => $commandName,
                        'File Path' => $build['path'],
                    ]);
                }
            } else {
                $io->error('Generation Failed: ' . $build['message']);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Fatal Error during Command Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
