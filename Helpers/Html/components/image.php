<?php
$src = $image['src'] ?? '';
$alt = $image['alt'] ?? '';
$attributes = $image['attributes'] ?? [];
?>

<?= html()->image($src, $alt, $attributes)->render()?>
