<?php
$value = $hidden['value'] ?? '';
$attributes = $hidden['attributes'] ?? [];
$attributes['value'] = $value;
$attributes['type'] = 'hidden';
?>
<?= html()->input($attributes)->render()?>
