<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This interface defines the contract for template loaders.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Contracts;

interface TemplateLoaderInterface
{
    public function load(string $path): string;
}
