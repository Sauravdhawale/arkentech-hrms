<?php
// Single shared visual system for all server-rendered application shells.
$themeAssetsRoot = dirname(__DIR__);
?>
<link rel="stylesheet" href="assets/theme.css?v=<?=filemtime($themeAssetsRoot.'/assets/theme.css')?>">
<script src="assets/theme.js?v=<?=filemtime($themeAssetsRoot.'/assets/theme.js')?>" defer></script>
