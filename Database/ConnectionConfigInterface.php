<?php

declare(strict_types=1);

namespace Database;

/**
 * Anchor Framework
 *
 * ConnectionConfigInterface defines the contract for connection configuration objects.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */
interface ConnectionConfigInterface
{
    public function getDriver(): string;

    public function getDsn(): string;

    public function getUser(): string;

    public function getPassword(): string;

    public function getTimezone(): string;

    public function getOptions(): array;

    public function isPersistent(): bool;

    public function getConfigArray(): array;
}
