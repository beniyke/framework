<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Framework component: image.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

$src = $image['src'] ?? '';
$alt = $image['alt'] ?? '';
$attributes = $image['attributes'] ?? [];
?>

<?= html()->image($src, $alt, $attributes)->render()?>
