<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Framework component: hidden.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

$value = $hidden['value'] ?? '';
$attributes = $hidden['attributes'] ?? [];
$attributes['value'] = $value;
$attributes['type'] = 'hidden';
?>
<?= html()->input($attributes)->render()?>
