<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Framework component: label.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

$content = $label['value'] ?? '';
$attributes = $label['attributes'] ?? [];
?>
<?= html()->label($content, $attributes)->render()?>
