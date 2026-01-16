<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new seeder class.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Database;

use Database\Helpers\DatabaseOperationConfig;
use Helpers\File\FileSystem;
use Helpers\File\Paths;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateSeederCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('seeder:create')
            ->setDescription('Creates a new seeder file from a template.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the seeder class (e.g., UserSeeder or User).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = resolve(DatabaseOperationConfig::class);
        $seederPath = $config->getSeedsPath();

        $io = new SymfonyStyle($input, $output);
        $className = $input->getArgument('name');

        if (! str_ends_with(strtolower($className), 'seeder')) {
            $className .= 'Seeder';
        }

        $io->title('Creating Seeder File');

        try {
            $fileName = "{$className}.php";
            $filePath = "{$seederPath}/{$fileName}";

            FileSystem::mkdir($seederPath, 0755, true);

            $template = $this->getStubContent('SeederTemplate.php.stub');

            if (! $template) {
                $io->error('Seeder template file not found.');

                return Command::FAILURE;
            }

            $content = str_replace('{{ class }}', $className, $template);

            FileSystem::put($filePath, $content);

            $io->success("Seeder created successfully: {$fileName}");
        } catch (RuntimeException $e) {
            $io->error('Failed to create seeder file: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function getStubContent(string $templateName): ?string
    {
        $templatePath = Paths::cliPath('Build/Templates/' . $templateName);

        if (! FileSystem::exists($templatePath)) {
            return null;
        }

        return FileSystem::get($templatePath);
    }
}
