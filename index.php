<?php
require __DIR__.'/auth.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
$user = $_SESSION['user'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PeopleFlow · Arkentech</title><link rel="icon" href="assets/favicon.svg"><link rel="stylesheet" href="assets/style.css"></head><body><div id="app"></div><script>window.PEOPLEFLOW_CSRF=<?= json_encode($_SESSION['csrf']) ?>;window.PEOPLEFLOW_USER=<?= json_encode($user, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script><script src="assets/app.js"></script></body></html>
