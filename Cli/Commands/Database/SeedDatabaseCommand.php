<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to run database seeders.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Database;

use Database\ConnectionInterface;
use Database\Helpers\DatabaseOperationConfig;
use Database\Migration\SeedManager;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SeedDatabaseCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('seeder:run')
            ->setDescription('Runs the database seeders.')
            ->addOption('class', 'c', InputOption::VALUE_OPTIONAL, 'The name of the seeder class to run (e.g., UserSeeder).', 'DatabaseSeeder');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = resolve(ConnectionInterface::class);
        $config = resolve(DatabaseOperationConfig::class);

        $io = new SymfonyStyle($input, $output);
        $seederClass = $input->getOption('class');
        $manager = new SeedManager($connection, $config->getSeedsPath());

        $io->title('Running Database Seeder');

        try {
            $io->comment(sprintf('Starting transaction and running seeder: %s...', $seederClass));

            $results = $connection->transaction(function () use ($manager, $seederClass) {
                return $manager->run($seederClass);
            });

            $io->success(sprintf(
                'Seeding complete for %s. Time taken: %s seconds.',
                $results['class'],
                $results['time']
            ));
        } catch (RuntimeException $e) {
            $io->error('Seeding Failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
