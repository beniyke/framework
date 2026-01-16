<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new middleware.
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

class CreateMiddlewareCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('middlewarename', InputArgument::REQUIRED, 'Name Of The Middleware to Generate.')
            ->addOption('api', null, InputOption::VALUE_NONE, 'Generate Middleware for API group.')
            ->addOption('web', null, InputOption::VALUE_NONE, 'Generate Middleware for Web group.')
            ->setName('middleware:create')
            ->setDescription('Creates new Middleware.')
            ->setHelp('This command allows you to create a new Middleware in App/Middleware...' . PHP_EOL . 'Usage: php dock middleware:create MyMiddleware --web');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $middlewareName = $input->getArgument('middlewarename');

        $group = 'Web';
        if ($input->getOption('api')) {
            $group = 'Api';
        }

        $io->title('Middleware Generator');
        $io->note(sprintf('Attempting to create Middleware "%s" in "%s" group.', $middlewareName, $group));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating middleware file...');
            $build = $generator->middleware($middlewareName, $group);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $middlewareName,
                    'Group' => $group,
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
            $io->error('Fatal Error during Middleware Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
