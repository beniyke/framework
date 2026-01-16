<?php
$attributes = $checkbox['attributes'] ?? [];
$attributes['type'] = 'checkbox';
$old_value = $checkbox['value'] ?? null;
$current_value = $attributes['value'] ?? null;

if (! isset($attributes['checked']) && $old_value !== null) {
    $old_values_array = (array) $old_value;
    $old_values_str = array_map('strval', $old_values_array);

    $is_checked = in_array((string) $current_value, $old_values_str, true);

    if ($is_checked) {
        $attributes['checked'] = true;
    }
}?>

<?= html()->input($attributes)->render()?>