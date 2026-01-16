<?php
$attributes = $radio['attributes'] ?? [];
$attributes['type'] = 'radio';
$old_value = $radio['value'] ?? null;
$current_value = $attributes['value'] ?? null;

if (! isset($attributes['checked']) && $old_value !== null) {
    $is_checked = ((string) $old_value === (string) $current_value);
    if ($is_checked) {
        $attributes['checked'] = true;
    }
}
?>

<?= html()->input($attributes)->render()?>
