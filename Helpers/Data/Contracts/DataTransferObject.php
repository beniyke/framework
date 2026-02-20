<?php

declare(strict_types=1);

namespace Helpers\Data\Contracts;

use Helpers\Data\Data;

interface DataTransferObject
{
    public function toArray(): array;

    public function getData(): Data;

    /**
     * Determine if the DTO is valid.
     *
     * @return bool
     */
    public function isValid(): bool;

    public function getErrors(): array;
}
