<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for resolving the appropriate Schema Grammar based on the driver.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Schema\Traits;

use Database\Connection;
use Database\Schema\Grammars\MysqlGrammar;
use Database\Schema\Grammars\PostgresGrammar;
use Database\Schema\Grammars\SchemaGrammar;
use Database\Schema\Grammars\SqliteGrammar;
use RuntimeException;

trait SchemaGrammarResolver
{
    protected function getGrammar(Connection $connection): SchemaGrammar
    {
        $driver = $connection->getDriver();

        return match ($driver) {
            'mysql' => new MysqlGrammar(),
            'sqlite' => new SqliteGrammar(),
            'pgsql' => new PostgresGrammar(),
            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };
    }
}
