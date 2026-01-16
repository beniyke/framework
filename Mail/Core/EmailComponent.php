<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Enterprise Email Component Builder
 * A fluent interface to build complex, responsive email bodies without writing HTML.
 * Includes helpers for branding, layouts, and transactional data.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Core;

class EmailComponent
{
    private string $build = '';

    private bool $escape;

    public function __construct(bool $escape = true)
    {
        $this->escape = $escape;
    }

    public static function make(bool $escape = true): self
    {
        return new static($escape);
    }

    private function escape(string $value): string
    {
        return $this->escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
    }

    /**
     * 1. Status Bar: Success, Warning, or Error banners.
     */
    public function status(string $message, string $type = 'success'): self
    {
        $colors = [
            'success' => ['bg' => '#dcfce7', 'text' => '#166534'],
            'warning' => ['bg' => '#fef9c3', 'text' => '#854d0e'],
            'error' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
        ];
        $s = $colors[$type] ?? $colors['success'];

        $this->build .= "
            <div style='padding: 14px; margin-bottom: 25px; background-color: {$s['bg']}; color: {$s['text']}; border-radius: 6px; text-align: center; font-family: sans-serif; font-size: 14px; font-weight: bold;'>
                " . $this->escape($message) . "
            </div>";

        return $this;
    }

    /**
     * 2. Hero Image: centered, responsive banners.
     */
    public function hero(string $url, ?string $alt = null): self
    {
        $this->build .= "
            <div style='margin-bottom: 25px; text-align: center;'>
                <img src='{$url}' alt='" . $this->escape($alt ?? '') . "' style='width: 100%; max-width: 600px; height: auto; display: block; border-radius: 8px; margin: 0 auto;'>
            </div>";

        return $this;
    }

    /**
     * 3. Standard Components: Greeting, Line, List.
     */
    public function greeting(string $value): self
    {
        $this->build .= '<h1 style="font-family: sans-serif; color: #111827; font-size: 18px; margin-bottom: 15px;">' . $this->escape($value) . '</h1>';

        return $this;
    }

    public function line(string $value): self
    {
        $this->build .= '<p style="font-family: sans-serif; font-size: 16px; line-height: 1.6; color: #374151; margin-bottom: 15px;">' . $this->escape($value) . '</p>';

        return $this;
    }

    public function list(array $items): self
    {
        $this->build .= '<ul style="font-family: sans-serif; font-size: 16px; color: #374151; margin-bottom: 15px;">';
        foreach ($items as $item) {
            $this->build .= '<li style="margin-bottom: 5px;">' . $this->escape((string) $item) . '</li>';
        }
        $this->build .= '</ul>';

        return $this;
    }

    /**
     * 4. Data Layouts: Table (Invoices) and Attributes (Metadata).
     */
    public function table(array $data): self
    {
        $this->build .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 25px; border-top: 1px solid #e5e7eb;">';
        foreach ($data as $key => $val) {
            $this->build .= "
                <tr>
                    <td style='padding: 10px 0; border-bottom: 1px solid #e5e7eb; font-family: sans-serif; font-size: 14px; color: #6b7280;'>{$this->escape((string)$key)}</td>
                    <td align='right' style='padding: 10px 0; border-bottom: 1px solid #e5e7eb; font-family: sans-serif; font-size: 14px; font-weight: bold; color: #111827;'>{$this->escape((string)$val)}</td>
                </tr>";
        }
        $this->build .= '</table>';

        return $this;
    }

    public function attributes(array $attributes): self
    {
        $this->build .= '<div style="margin-bottom: 25px; padding: 15px; background-color: #f9fafb; border-radius: 8px; border: 1px solid #f3f4f6;">';
        foreach ($attributes as $label => $value) {
            $this->build .= "
                <p style='margin: 4px 0; font-family: sans-serif; font-size: 13px;'>
                    <span style='color: #6b7280;'>{$this->escape($label)}:</span> 
                    <strong style='color: #374151;'>{$this->escape((string)$value)}</strong>
                </p>";
        }
        $this->build .= '</div>';

        return $this;
    }

    /**
     * 5. Callouts: Panel, Action Button, and Dividers.
     */
    public function panel(string $value): self
    {
        $this->build .= "
            <table width='100%' cellpadding='0' cellspacing='0' role='presentation' style='margin-bottom: 25px;'>
                <tr>
                    <td style='padding: 15px; background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px;'>
                        <p style='margin: 0; font-family: sans-serif; font-size: 14px; color: #1e40af;'>{$this->escape($value)}</p>
                    </td>
                </tr>
            </table>";

        return $this;
    }

    public function action(string $value, string $url): self
    {
        $v = $this->escape($value);
        $u = $this->escape($url);
        $this->build .= "
            <table align='center' width='100%' border='0' cellpadding='0' cellspacing='0' role='presentation' style='margin: 30px 0;'>
                <tr>
                    <td align='center'>
                        <a href='{$u}' style='background-color: #2563eb; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-family: sans-serif; font-weight: bold; font-size: 16px; display: inline-block;'>{$v}</a>
                    </td>
                </tr>
            </table>
            <p style='font-size: 12px; color: #9ca3af; text-align: center;'>Trouble clicking? <a href='{$u}' style='color: #2563eb;'>{$u}</a></p>";

        return $this;
    }

    public function divider(): self
    {
        $this->build .= '<hr style="border: none; border-top: 1px solid #f3f4f6; margin: 30px 0;">';

        return $this;
    }

    public function subcopy(string $value): self
    {
        $this->build .= '<p style="font-family: sans-serif; font-size: 12px; color: #9ca3af; line-height: 1.5; margin-top: 20px;">' . $this->escape($value) . '</p>';

        return $this;
    }

    public function raw(string $value): self
    {
        $this->build .= $value;

        return $this;
    }

    public function html(mixed $value, callable $callback): self
    {
        $this->build .= $callback($value);

        return $this;
    }

    public function render(): string
    {
        return $this->build;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
