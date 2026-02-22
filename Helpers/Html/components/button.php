<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Framework component: button.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

$content = $button['value'] ?? '';
$attributes = $button['attributes'] ?? [];
if (! isset($attributes['type'])) {
    $attributes['type'] = 'button';
}
?>
<?= html()->button($content, $attributes)->render()?>
