<?php
$content = $submit['value'] ?? '';
$attributes = $submit['attributes'] ?? [];

if (! isset($attributes['type'])) {
    $attributes['type'] = 'submit';
}
?>
<?= html()->button($content, $attributes)->render()?>
