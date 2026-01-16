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

class EnvEncryptCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('env:encrypt')
            ->setDescription('Encrypt an environment file to be included in version control')
            ->addOption('key', null, InputOption::VALUE_OPTIONAL, 'The encryption key')
            ->addOption('cipher', null, InputOption::VALUE_OPTIONAL, 'The encryption cipher', 'AES-256-CBC')
            ->addOption('env', null, InputOption::VALUE_OPTIONAL, 'The environment file to encrypt', '.env')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite the existing encrypted file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Environment File Encryption');

        $cipher = $input->getOption('cipher');
        $key = $input->getOption('key');
        $env = $input->getOption('env');

        // Resolve paths
        $envPath = Paths::basePath($env);
        $encryptedPath = $envPath . '.encrypted';
        if (! file_exists($envPath)) {
            $io->error(sprintf('Environment file not found at [%s]', $envPath));

            return Command::FAILURE;
        }

        if (file_exists($encryptedPath) && ! $input->getOption('force')) {
            $io->error('Encrypted environment file already exists. Use --force to overwrite.');

            return Command::FAILURE;
        }

        if (! $key) {
            $key = random_bytes(32);
            $io->section('Generating a new encryption key');
            $io->text('Since no key was provided, a new one has been generated for you.');
            $io->info('Key: ' . 'base64:' . base64_encode($key));
            $io->warning('Keep this key safe! You will need it to decrypt the file.');
        } elseif (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        try {
            $encryptor = new FileEncryptor(new FileReadWriteAdapter());
            $encryptor->password($key);
            $encryptor->encrypt($envPath, $encryptedPath);

            $io->success(sprintf('Environment file encrypted successfully: %s', $envPath));
            $io->text(sprintf('Encrypted file saved to: %s', $encryptedPath));

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Encryption failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
