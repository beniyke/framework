<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Framework component: select.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

$selected_value = $select['value'] ?? $select['options']['selected'] ?? null;

$options = $select['options'] ?? ['data' => [], 'description' => false];

if ($options['description']) {
    $options['data'] = arr($options['data'])->prepend(['' => 'SELECT'])->get();
}

$options['selected'] = $selected_value;

$html = html()
    ->select(
        html()->options($options)->render(),
        $select['attributes'] ?? []
    )
    ->render();
?>
<?= $html?>
