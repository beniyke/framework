<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Service provider for database components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Providers;

use Cli\Build\DBA;
use Cli\Helpers\CommandMapper;
use Core\Ioc\ContainerInterface;
use Core\Services\CliServiceInterface;
use Core\Services\ConfigServiceInterface;
use Core\Services\ServiceProvider;
use Core\Support\Adapters\Interfaces\SapiInterface;
use Database\BaseModel;
use Database\ConnectionConfig;
use Database\ConnectionConfigInterface;
use Database\ConnectionFactory;
use Database\ConnectionInterface;
use Database\DB;
use Database\Helpers\DatabaseOperationConfig;
use Database\NullConnection;
use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Helpers\File\Contracts\LoggerInterface;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $container = $this->container;
        $config = $container->get(ConfigServiceInterface::class);
        $dbConfig = $config->get('database');
        $isCli = $container->get(SapiInterface::class)->isCli();
        $cliService = $container->get(CliServiceInterface::class);
        $commandName = null;
        $requiresDatabaseConnection = true;
        $isRunningTest = false;

        if ($isCli) {
            $mapper = new CommandMapper($config->get('command.db_dependent'));
            $commandName = $cliService->getCommandName() ?? 'dock';
            $requiresDatabaseConnection = $mapper->requiresDatabaseConnection($commandName);
            $isRunningTest = $mapper->isRunningTest($commandName);
        }

        $this->container->singleton(ConnectionFactory::class, function () {
            return new ConnectionFactory();
        });

        $this->container->singleton(ConnectionConfigInterface::class, function (ContainerInterface $container) use ($dbConfig, $isRunningTest) {

            if ($isRunningTest) {
                return ConnectionConfig::fromTestConfig();
            }

            return ConnectionConfig::fromFullConfig($dbConfig);
        });

        $this->container->singleton(ConnectionInterface::class, function (ContainerInterface $container) use ($dbConfig, $requiresDatabaseConnection) {
            $connectionConfig = $container->get(ConnectionConfigInterface::class);

            if (! $requiresDatabaseConnection) {
                $driver = $dbConfig['driver'] ?? 'mysql';

                return new NullConnection($driver);
            }

            return ConnectionFactory::create($connectionConfig);
        });

        $this->container->singleton(DatabaseOperationConfig::class, function ($container) use ($dbConfig) {
            return new DatabaseOperationConfig($dbConfig);
        });

        $this->container->singleton(DBA::class, function ($container) use ($dbConfig) {
            return new DBA(
                $container->get(PathResolverInterface::class),
                $container->get(FileManipulationInterface::class),
                $container->get(FileMetaInterface::class),
                $container->get(FileReadWriteInterface::class),
                $dbConfig,
                $container->get(DatabaseOperationConfig::class),
                $container->get(ConnectionInterface::class)
            );
        });
    }

    public function boot(): void
    {
        $connection = $this->container->get(ConnectionInterface::class);

        if ($connection instanceof NullConnection) {
            return;
        }

        $logger = $this->container->get(LoggerInterface::class);

        DB::setDefaultConnection($connection);
        BaseModel::setConnection($connection);

        $operationConfig = $this->container->get(DatabaseOperationConfig::class);
        $thresholdMs = $operationConfig->getSlowQueryThreshold();
        $threshold = $thresholdMs / 1000;

        DB::whenQueryingForLongerThan($threshold, function (array $logEntry) use ($logger, $threshold) {
            $logger->setLogFile('query.log');
            $logger->warning("SLOW QUERY WARNING: Query took {$logEntry['time_ms']}ms (Threshold: {$threshold}s)", [
                'sql' => $logEntry['sql'],
                'bindings' => $logEntry['bindings'],
                'connection' => $logEntry['connection'],
            ]);
        });
    }
}
