<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Framework component: link.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

$content = $link['value'] ?? '';
$href = $link['href'] ?? '#';
$attributes = $link['attributes'] ?? [];
?>

<?= html()->link($content, $href, $attributes)->render()?>