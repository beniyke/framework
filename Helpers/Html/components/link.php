<?php
$content = $link['value'] ?? '';
$href = $link['href'] ?? '#';
$attributes = $link['attributes'] ?? [];
?>

<?= html()->link($content, $href, $attributes)->render()?>