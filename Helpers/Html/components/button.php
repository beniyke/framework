<?php
$content = $button['value'] ?? '';
$attributes = $button['attributes'] ?? [];
if (! isset($attributes['type'])) {
    $attributes['type'] = 'button';
}
?>
<?= html()->button($content, $attributes)->render()?>
