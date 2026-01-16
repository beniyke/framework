<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to release the database migration lock.
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

class MigrationUnlockCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('migration:unlock')
            ->setDescription('Releases a database lock, allowing migrations to run.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = resolve(ConnectionInterface::class);

        $io = new SymfonyStyle($input, $output);
        $io->title('Releasing Migration Lock');

        try {
            $locker = new MigrationLocker($connection);
            $locker->releaseLock();
            $io->success('Migration lock successfully released.');
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
