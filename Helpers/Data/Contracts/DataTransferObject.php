<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Determine if the DTO is valid.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Data\Contracts;

use Helpers\Data\Data;

interface DataTransferObject
{
    public function toArray(): array;

    public function getData(): Data;

    public function isValid(): bool;

    public function getErrors(): array;
}
