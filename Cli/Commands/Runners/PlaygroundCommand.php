<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to start the application playground (REPL).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Runners;

use Core\Ioc\Container;
use Core\Services\ConfigServiceInterface;
use Core\Support\Environment;
use Exception;
use Helpers\File\FileSystem;
use Helpers\File\Paths;
use Psy\Configuration;
use Psy\Shell;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PlaygroundCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('execute', InputArgument::OPTIONAL, 'The code file name within the _playground/ directory to be executed.')
            ->setName('playground')
            ->setDescription('Playground to interact with your application via command line')
            ->setHelp('This command initiates the playground (REPL) to interact with your application via command line, or executes a specified script from the _playground/ directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Application Playground');

        if (Environment::isProduction()) {
            $io->error('Playground is disabled in production environment for security reasons.');

            return self::FAILURE;
        }

        try {
            $container = Container::getInstance();
            $config = $container->get(ConfigServiceInterface::class);

            $psyConfig = new Configuration([
                'startupMessage' => $config->get('playground.startup_message', 'Playground - Type "exit" to quit'),
                'historyFile' => $config->get('playground.history_file', 'App/storage/playground_history'),
            ]);

            $shell = new Shell($psyConfig);
            $this->addHelperFunctions($shell);
            $this->setupAutoImports($shell, $config);
            $file = $input->getArgument('execute');

            if (! empty($file)) {
                return $this->executeScript($file, $shell, $io);
            } else {
                return $this->runInteractiveMode($shell, $io);
            }
        } catch (Exception $e) {
            $io->error('An error occurred in the Playground: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function executeScript(string $file, Shell $shell, SymfonyStyle $io): int
    {
        $filePath = '_playground/' . $file;

        $io->note(sprintf('Attempting to execute script: "%s"', $filePath));

        if (FileSystem::exists($filePath)) {
            $io->text('Executing script...');
            $content = FileSystem::get($filePath);

            $shell->setOutput($io);
            $shell->execute($content);
            $io->success('Script executed successfully.');

            return self::SUCCESS;
        } else {
            $io->error(sprintf('File not found: %s', $filePath));

            return self::FAILURE;
        }
    }

    private function runInteractiveMode(Shell $shell, SymfonyStyle $io): int
    {
        $io->section('Interactive Shell Started');
        $io->text('Entering Psy Shell. Type "exit" to quit.');
        $shell->run();

        return self::SUCCESS;
    }

    private function addHelperFunctions(Shell $shell): void
    {
        $helpersPath = Paths::cliPath('Helpers' . DIRECTORY_SEPARATOR . 'playground_helper.php');

        if (! FileSystem::exists($helpersPath)) {
            return;
        }

        $shell->addCode(FileSystem::get($helpersPath));
    }

    private function setupAutoImports(Shell $shell, ConfigServiceInterface $config): void
    {
        $imports = $config->get('playground.auto_imports', []);

        foreach ($imports as $class) {
            if (class_exists($class)) {
                $shell->addCode("use {$class};");
            }
        }
    }
}
