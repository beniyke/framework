<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to delete an existing database.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Commands\Database;

use Cli\Build\DBA;
use Core\Support\Environment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class DeleteDatabaseCommand extends Command
{
    private function isProductionEnvironment(): bool
    {
        return Environment::isProduction();
    }

    protected function configure(): void
    {
        $this->addArgument('databasename', InputArgument::OPTIONAL, 'Name Of The Database to Delete.')
            ->setName('database:delete')
            ->setDescription('Delete an existing database.')
            ->setHelp('This command allows you to delete an existing database.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Allows the command to run in a production environment.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dbName = $input->getArgument('databasename');
        $targetDb = $dbName ?? '(Default/Config Name)';

        $force = $input->getOption('force');
        $isProduction = $this->isProductionEnvironment();

        if ($isProduction && ! $force) {
            $io->error('Cannot run delete command in production without the --force flag.');

            return Command::FAILURE;
        }

        $io->title('Database Deletion Tool');
        $io->note(sprintf('Targeting database for deletion: %s', $targetDb));

        try {
            $question = sprintf('Are you absolutely sure you want to delete the database "%s"? (This action is irreversible!)', $targetDb);

            if (($force || $io->confirm($question, false))) {
                $io->text('Deletion confirmed. Executing DBA delete...');

                $dba = resolve(DBA::class);
                $build = $dba->deleteDatabase($dbName);

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->error('Operation Failed: ' . $build['message']);
                }
            } else {
                $io->comment('Deletion cancelled by user.');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error('A fatal error occurred during database deletion: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
