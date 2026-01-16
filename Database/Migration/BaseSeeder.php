<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Base class for Database Seeders.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Migration;

use Database\ConnectionInterface;

abstract class BaseSeeder
{
    protected ConnectionInterface $connection;

    protected SeedManager $manager;

    public function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    public function setManager(SeedManager $manager): void
    {
        $this->manager = $manager;
    }

    abstract public function run(): void;

    protected function call(array|string $seeders): void
    {
        $seeders = is_array($seeders) ? $seeders : [$seeders];

        foreach ($seeders as $seederClass) {
            $this->manager->run($seederClass);
        }
    }

    protected function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }
}
