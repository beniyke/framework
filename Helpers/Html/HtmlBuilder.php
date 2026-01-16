<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fluent builder for generating HTML elements.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Html;

class HtmlBuilder
{
    private string $html = '';

    public function element(string $tag, string $content = '', array $attributes = [], bool $self_closing = false): self
    {
        $this->html .= "<$tag" . $this->buildAttributes($attributes);

        if ($self_closing) {
            $this->html .= '>';
        } else {
            $this->html .= ">$content</$tag>";
        }

        return $this;
    }

    public function open(string $tag, array $attributes = []): self
    {
        $this->html .= "<$tag" . $this->buildAttributes($attributes) . '>';

        return $this;
    }

    private function buildAttributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $key => $value) {
            $escaped_key = htmlspecialchars($key);

            if ($value === true) {
                $html .= " $escaped_key";
            } elseif ($value === false) {
                continue;
            } else {
                $escaped_value = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                $html .= " $escaped_key=\"$escaped_value\"";
            }
        }

        return $html;
    }

    public function addRawHtml(string $html): self
    {
        $this->html .= $html;

        return $this;
    }

    public function startForm(array $attributes = []): self
    {
        return $this->open('form', $attributes);
    }

    public function closeForm(): self
    {
        $this->html .= '</form>';

        return $this;
    }

    public function options(array $options): self
    {
        $selected = (array) ($options['selected'] ?? []);
        $selected_str = array_map('strval', $selected);

        foreach ($options['data'] as $value => $content) {
            $attributes = compact('value');

            if (in_array((string) $value, $selected_str, true)) {
                $attributes['selected'] = true;
            }

            $this->option($content, $attributes);
        }

        return $this;
    }

    public function input(array $attributes = []): self
    {
        return $this->element('input', '', $attributes, true);
    }

    public function textArea(string $content = '', array $attributes = []): self
    {
        $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        return $this->element('textarea', $safe_content, $attributes);
    }

    public function button(string $content, array $attributes = []): self
    {
        $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        return $this->element('button', $safe_content, $attributes);
    }

    public function label(string $content, array $attributes = []): self
    {
        $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        return $this->element('label', $safe_content, $attributes);
    }

    public function option(string $content, array $attributes = []): self
    {
        $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        return $this->element('option', $safe_content, $attributes);
    }

    public function optgroup(string $content, array $attributes = []): self
    {
        return $this->element('optgroup', $content, $attributes);
    }

    public function fieldset(string $content, array $attributes = []): self
    {
        return $this->element('fieldset', $content, $attributes);
    }

    public function legend(string $content, array $attributes = []): self
    {
        $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        return $this->element('legend', $safe_content, $attributes);
    }

    public function select(string $options, array $attributes = []): self
    {
        if (isset($attributes['placeholder'])) {
            unset($attributes['placeholder']);
        }

        return $this->element('select', $options, $attributes);
    }

    public function startTable(array $attributes = []): self
    {
        return $this->open('table', $attributes);
    }

    public function closeTable(): self
    {
        $this->html .= '</table>';

        return $this;
    }

    public function startRow(array $attributes = []): self
    {
        return $this->open('tr', $attributes);
    }

    public function closeRow(): self
    {
        $this->html .= '</tr>';

        return $this;
    }

    public function headerCell(string $content, array $attributes = []): self
    {
        $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        return $this->element('th', $safe_content, $attributes);
    }

    public function dataCell(string $content, array $attributes = []): self
    {
        $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        return $this->element('td', $safe_content, $attributes);
    }

    public function paragraph(string $content, array $attributes = []): self
    {
        $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        return $this->element('p', $safe_content, $attributes);
    }

    public function div(string $content, array $attributes = []): self
    {
        return $this->element('div', $content, $attributes);
    }

    public function span(string $content, array $attributes = []): self
    {
        $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        return $this->element('span', $safe_content, $attributes);
    }

    public function link(string $content, string $href, array $attributes = []): self
    {
        return $this->element('a', $content, array_merge($attributes, ['href' => $href]));
    }

    public function section(string $content, array $attributes = []): self
    {
        return $this->element('section', $content, $attributes);
    }

    public function article(string $content, array $attributes = []): self
    {
        return $this->element('article', $content, $attributes);
    }

    public function header(string $content, array $attributes = []): self
    {
        return $this->element('header', $content, $attributes);
    }

    public function footer(string $content, array $attributes = []): self
    {
        return $this->element('footer', $content, $attributes);
    }

    public function nav(string $content, array $attributes = []): self
    {
        return $this->element('nav', $content, $attributes);
    }

    public function aside(string $content, array $attributes = []): self
    {
        return $this->element('aside', $content, $attributes);
    }

    public function image(string $src, string $alt = '', array $attributes = []): self
    {
        return $this->element('img', '', array_merge($attributes, ['src' => $src, 'alt' => $alt]), true);
    }

    public function video(string $src, string $type = '', array $attributes = []): self
    {
        return $this->element('video', '', array_merge($attributes, ['src' => $src, 'type' => $type]));
    }

    public function audio(string $src, array $attributes = []): self
    {
        return $this->element('audio', '', array_merge($attributes, ['src' => $src]));
    }

    public function meta(array $attributes = []): self
    {
        return $this->element('meta', '', $attributes, true);
    }

    public function linkTag(array $attributes = []): self
    {
        return $this->element('link', '', $attributes, true);
    }

    public function script(string $content, array $attributes = []): self
    {
        return $this->element('script', $content, $attributes);
    }

    public function style(string $content, array $attributes = []): self
    {
        return $this->element('style', $content, $attributes);
    }

    public function render(): string
    {
        return $this->html;
    }

    public function reset(): self
    {
        $this->html = '';

        return $this;
    }
}
