<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Framework component: submit.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

$content = $submit['value'] ?? '';
$attributes = $submit['attributes'] ?? [];

if (! isset($attributes['type'])) {
    $attributes['type'] = 'submit';
}
?>
<?= html()->button($content, $attributes)->render()?>
