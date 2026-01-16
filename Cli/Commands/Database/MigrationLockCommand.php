<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to acquire a database lock for migrations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Database;

use Database\ConnectionInterface;
use Database\Migration\MigrationLocker;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationLockCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('migration:lock')
            ->setDescription('Acquires a database lock to prevent concurrent migrations.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = resolve(ConnectionInterface::class);

        $io = new SymfonyStyle($input, $output);
        $io->title('Acquiring Migration Lock');

        try {
            $locker = new MigrationLocker($connection);
            $locker->acquireLock();
            $io->success('Migration lock successfully acquired.');
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            $io->warning('If you are sure no migrations are running, use migrate:unlock.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
