<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to generate a new API Key.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Generators;

use Bridge\Models\ApiKey;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateApiKeyCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('api:generate-key')
            ->setDescription('Generate a new dynamic API key')
            ->addArgument('name', InputArgument::REQUIRED, 'Name/description for the API key')
            ->setHelp('This command generates a new API key for dynamic authentication.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        $io->title('API Key Generator');
        $io->note(sprintf('Generating API key for: %s', $name));

        try {
            $result = ApiKey::generate($name);

            $io->success('API Key Generated Successfully!');
            $io->newLine();

            $io->definitionList(
                ['Name' => $result['model']->name],
                ['ID' => $result['model']->id],
                ['Created' => $result['model']->created_at]
            );

            $io->newLine();
            $io->warning('⚠️  IMPORTANT: Save this key now - it will not be shown again!');
            $io->newLine();

            $io->writeln('  <fg=green;options=bold>Key:</> ' . $result['key']);
            $io->newLine();

            $io->comment('Use this key in the Authorization header:');
            $io->writeln('  Authorization: Bearer ' . $result['key']);
            $io->newLine();

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('Failed to generate API key: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
