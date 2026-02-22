<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Framework component: textarea.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

$content = $textarea['value'] ?? '';
$attributes = $textarea['attributes'] ?? [];
?>
<?= html()->textArea($content, $attributes)->render()?>
