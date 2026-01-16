<?php
$value = $input['value'] ?? '';
$attributes = $input['attributes'] ?? [];
$attributes['value'] = $value;
?>
<?= html()->input($attributes)->render()?>
