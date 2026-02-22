<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Framework component: input.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

$value = $input['value'] ?? '';
$attributes = $input['attributes'] ?? [];
$attributes['value'] = $value;
?>
<?= html()->input($attributes)->render()?>
