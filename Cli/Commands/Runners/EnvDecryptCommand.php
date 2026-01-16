<?php

declare(strict_types=1);

namespace Cli\Commands\Runners;

use Exception;
use Helpers\Encryption\Drivers\FileEncryptor;
use Helpers\File\Adapters\FileReadWriteAdapter;
use Helpers\File\Paths;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EnvDecryptCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('env:decrypt')
            ->setDescription('Decrypt an environment file being controlled by version control')
            ->addOption('key', null, InputOption::VALUE_OPTIONAL, 'The encryption key')
            ->addOption('cipher', null, InputOption::VALUE_OPTIONAL, 'The encryption cipher', 'AES-256-CBC')
            ->addOption('env', null, InputOption::VALUE_OPTIONAL, 'The environment file to decrypt', '.env')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite the existing environment file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Environment File Decryption');

        $cipher = $input->getOption('cipher');
        $key = $input->getOption('key');
        $env = $input->getOption('env');

        // Resolve paths
        $envPath = Paths::basePath($env);
        $encryptedPath = $envPath . '.encrypted';

        if (! $key) {
            $key = getenv('ANCHOR_ENV_ENCRYPTION_KEY') ?: getenv('APP_ENV_ENCRYPTION_KEY');
        }

        if (! $key) {
            $io->error('A decryption key is required. Pass it via --key or set ANCHOR_ENV_ENCRYPTION_KEY environment variable.');

            return Command::FAILURE;
        }

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        if (! file_exists($encryptedPath)) {
            $io->error(sprintf('Encrypted environment file not found at [%s]', $encryptedPath));

            return Command::FAILURE;
        }

        if (file_exists($envPath) && ! $input->getOption('force')) {
            $io->error('Environment file already exists. Use --force to overwrite.');

            return Command::FAILURE;
        }

        try {
            $encryptor = new FileEncryptor(new FileReadWriteAdapter());
            $encryptor->password($key);
            $decrypted = $encryptor->decrypt($encryptedPath);

            file_put_contents($envPath, $decrypted);

            $io->success(sprintf('Environment file decrypted successfully: %s', $envPath));
            $io->text(sprintf('Decrypted from: %s', $encryptedPath));

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Decryption failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
