<?php
$content = $label['value'] ?? '';
$attributes = $label['attributes'] ?? [];
?>
<?= html()->label($content, $attributes)->render()?>
