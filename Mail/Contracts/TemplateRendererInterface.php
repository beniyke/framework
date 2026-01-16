<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This interface defines the contract for template renderers.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Contracts;

interface TemplateRendererInterface
{
    public function render(string $template, array $data): string;
}
