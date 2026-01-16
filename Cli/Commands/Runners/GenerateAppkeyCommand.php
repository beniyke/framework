<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to generate a new application key.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Runners;

use Core\Services\DotenvInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateAppKeyCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('key:generate')
            ->setDescription('Generates a new secure APP_KEY.')
            ->addOption('show', null, InputOption::VALUE_NONE, 'Display the key instead of modifying files.')
            ->setHelp('This command calls the Dotenv service to create a fresh 256-bit APP_KEY.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Application Key Generator');

        if ($input->getOption('show')) {
            $key = 'base64:' . base64_encode(random_bytes(32));
            $io->comment('Here is your new APP_KEY. Copy it to your .env file.');
            $io->writeln($key);

            return self::SUCCESS;
        }

        $io->section('Attempting to generate and save a new APP_KEY...');

        try {
            $dotenv = resolve(DotenvInterface::class);
            $dotenv->generateAndSaveAppKey();
            $newKey = $dotenv->getValue('APP_KEY');
            $displayKey = substr($newKey, 0, 10) . '...';

            $io->success('Successfully generated and saved new APP_KEY to .env file.');
            $io->note("New APP_KEY starts with: {$displayKey} (Key is 256-bit, 64-character Hexadecimal)");

            return self::SUCCESS;
        } catch (Exception $e) {
            $io->error('A fatal error occurred during key generation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
