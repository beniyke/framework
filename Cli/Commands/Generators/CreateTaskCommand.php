<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new Task/Job.
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

class CreateTaskCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('taskname', InputArgument::REQUIRED, 'Name Of The Task to Generate.')
            ->addArgument('modulename', InputArgument::OPTIONAL, 'Name Of The Module to Generate The Task to.')
            ->setName('task:create')
            ->setDescription('Creates new task.')
            ->setHelp('This command allows you to create a new task.' . PHP_EOL . 'Note: To create a task for a module, specify the module name e.g. "deleteInactiveAccount Account". Omitting the module will create a global task in "App/Tasks".');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $taskName = $input->getArgument('taskname');
        $moduleName = $input->getArgument('modulename');

        $io->title('Task/Job Generator');
        $io->note(sprintf('Attempting to create Task Class "%s" in %s.', $taskName, $moduleName ? "module \"$moduleName\"" : 'global tasks directory'));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating task class file...');
            $build = $generator->task($taskName, $moduleName);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $taskName . 'Task',
                    'Module' => $moduleName ?? 'Global',
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
            $io->error('Fatal Error during Task Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
