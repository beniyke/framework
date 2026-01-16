<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class renders email templates by replacing placeholders with data.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Services;

use Mail\Contracts\TemplateRendererInterface;

class TemplateRenderer implements TemplateRendererInterface
{
    public function render(string $template, array $data): string
    {
        $placeholders = array_map(
            fn ($key) => '[' . strtoupper($key) . ']',
            array_keys($data)
        );

        $values = array_values($data);

        return str_replace($placeholders, $values, $template);
    }
}
