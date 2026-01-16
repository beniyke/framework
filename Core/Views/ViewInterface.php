<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for the View Engine.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Views;

use Helpers\Http\Response;

interface ViewInterface
{
    public function path(string $path): self;

    public function template(string $template): self;

    public function denyAccessIf(bool $value, string $template = 'deny'): self;

    public function data(array $data): self;

    public function content(): string;

    public function render(): Response;
}
