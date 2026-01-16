<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a new service provider.
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

class CreateProviderCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('providername', InputArgument::REQUIRED, 'Name of the provider class to generate')
            ->setName('provider:create')
            ->setDescription('Creates new provider class.')
            ->setHelp('This command allows you to create a new provider class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $providerName = $input->getArgument('providername');

        $io->title('Service Provider Generator');
        $io->note(sprintf('Attempting to create provider class: "%s".', $providerName));

        try {
            $generator = Generators::getInstance();
            $io->text('Generating provider file...');
            $build = $generator->provider($providerName);

            if ($build['status']) {
                $io->success($build['message']);

                $details = [
                    'Class Name' => $providerName,
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
            $io->error('Fatal Error during Provider Creation: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
