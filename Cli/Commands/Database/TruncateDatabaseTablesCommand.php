<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to truncate database tables.
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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class TruncateDatabaseTablesCommand extends Command
{
    private function isProductionEnvironment(): bool
    {
        return Environment::isProduction();
    }

    protected function configure(): void
    {
        $this->addArgument('tablename', InputArgument::OPTIONAL, 'Name Of The Database Table(s) to Truncate (comma-separated).')
            ->setName('database:truncate')
            ->setDescription('Truncates database tables or a database table.')
            ->setHelp('This command allows you to truncate database tables or a database table. Omit argument to truncate all tables.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Allows the command to run in a production environment.');
    }

    protected function truncateConfirmation(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('<fg=red>WARNING: Are you absolutely sure you want to truncate the specified database tables? This action is irreversible.</> [y]/n ', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tableName = $input->getArgument('tablename');
        $tablesToTruncate = $tableName ?: 'ALL tables';

        $force = $input->getOption('force');
        $isProduction = $this->isProductionEnvironment();

        if ($isProduction && ! $force) {
            $io->error('Cannot run truncate command in production without the --force flag.');

            return Command::FAILURE;
        }

        $io->title('Database Truncation');
        $io->note(sprintf('Target(s) for truncation: %s', $tablesToTruncate));

        $io->block('DANGER! All data in the target table(s) will be permanently deleted.', 'WARNING', 'fg=white;bg=red', ' ', true);

        try {
            $question = $this->truncateConfirmation();

            if (($force || $io->askQuestion($question))) {
                $io->text('Truncation confirmed. Executing...');
                $dba = resolve(DBA::class);
                $build = $dba->truncateDatabaseTable($tableName);

                if ($build['status']) {
                    $io->success($build['message']);
                } else {
                    $io->warning('Operation Failed: ' . $build['message']);
                }
            } else {
                $io->comment('Operation cancelled by user. No tables were truncated.');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error('Truncation Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
