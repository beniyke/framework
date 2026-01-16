<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for SAPI interaction.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Support\Adapters\Interfaces;

interface SapiInterface
{
    public function isCgi(): bool;

    public function isCli(): bool;

    public function isPhpServer(): bool;
}
