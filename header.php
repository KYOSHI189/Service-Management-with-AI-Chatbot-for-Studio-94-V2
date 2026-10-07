<?php
// Shared HTML <head> — included by index.php
// Variables available: $user, $role, $page, $title
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= clean($title) ?> — Studio 94 SnapTrack</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/styles.css"/>
</head>
<body>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>
<div class="app-layout">
