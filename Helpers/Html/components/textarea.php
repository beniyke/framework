<?php
$content = $textarea['value'] ?? '';
$attributes = $textarea['attributes'] ?? [];
?>
<?= html()->textArea($content, $attributes)->render()?>
