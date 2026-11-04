<?php
// Single shared visual system for all server-rendered application shells.
$themeAssetsRoot = dirname(__DIR__);
?>
<link rel="icon" type="image/svg+xml" sizes="any" href="assets/favicon.svg?v=<?=filemtime($themeAssetsRoot.'/assets/favicon.svg')?>">
<link rel="stylesheet" href="assets/theme.css?v=<?=filemtime($themeAssetsRoot.'/assets/theme.css')?>">
<script src="assets/theme.js?v=<?=filemtime($themeAssetsRoot.'/assets/theme.js')?>" defer></script>


<link rel="stylesheet" href="assets/my-punches.css?v=<?=filemtime($themeAssetsRoot.'/assets/my-punches.css')?>">
<script src="assets/my-punches.js?v=<?=filemtime($themeAssetsRoot.'/assets/my-punches.js')?>" defer></script>
