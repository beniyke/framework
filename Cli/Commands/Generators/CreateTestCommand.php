<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new Test file.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Generators;

use Exception;
use Helpers\File\FileSystem;
use Helpers\File\Paths;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateTestCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('test:create')
            ->setDescription('Create a new test file')
            ->setHelp('This command creates a new Pest test file.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the test (e.g., UserServiceTest)')
            ->addOption('unit', 'u', InputOption::VALUE_NONE, 'Create a unit test')
            ->addOption('feature', 'f', InputOption::VALUE_NONE, 'Create a feature test (default)')
            ->addOption('system', 's', InputOption::VALUE_NONE, 'Create a framework core (system) test')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Category for unit tests (e.g., Mail, Database)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $name = $input->getArgument('name');
            $isUnit = $input->getOption('unit');
            $isFeature = $input->getOption('feature');
            $isSystem = $input->getOption('system');
            $category = $input->getOption('category');

            // Default to feature if neither is specified
            if (! $isUnit && ! $isFeature) {
                $isFeature = true;
            }

            // Ensure name ends with 'Test'
            if (! str_ends_with($name, 'Test')) {
                $name .= 'Test';
            }

            // Determine the base test directory (App or System)
            $suite = $isSystem ? 'System' : 'App';
            $testType = $isUnit ? 'Unit' : 'Feature';

            // Determine the final directory
            if ($isUnit && $category) {
                $directory = Paths::testPath("{$suite}/{$testType}/{$category}");
            } else {
                $directory = Paths::testPath("{$suite}/{$testType}");
            }

            $filePath = $directory . DIRECTORY_SEPARATOR . $name . '.php';

            // Check if file already exists
            if (FileSystem::exists($filePath)) {
                $io->error("Test file already exists: {$filePath}");

                return self::FAILURE;
            }

            // Create directory if it doesn't exist
            if (! FileSystem::isDir($directory)) {
                FileSystem::mkdir($directory, 0755, true);
            }

            // Generate test content
            $content = $this->generateTestContent($name, $isUnit);

            // Write the file
            FileSystem::put($filePath, $content);

            $io->success('Test file created successfully!');
            $io->text("Location: {$filePath}");

            $testType = $isUnit ? 'Unit' : 'Feature';
            $io->note("Created a {$testType} test. You can run it with: php dock test {$filePath}");

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('An error occurred while creating the test file: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function generateTestContent(string $name, bool $isUnit): string
    {
        $testName = str_replace('Test', '', $name);
        $testType = $isUnit ? 'Unit' : 'Feature';

        return <<<PHP
<?php

/**
 * {$testType} tests for {$testName}
 */

describe('{$testName}', function () {
    test('example test', function () {
        expect(true)->toBeTrue();
    });
});

PHP;
    }
}
