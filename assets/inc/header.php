<?php
if (!defined('APP_BASE')) define('APP_BASE', '');
if (!isset($page_title)) $page_title = 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($page_title) ?></title>

  <!-- shared admin header/footer css -->
  <link rel="stylesheet" href="<?= APP_BASE ?>header.css">
  <link rel="stylesheet" href="<?= APP_BASE ?>footer.css"><!-- optional -->
</head>
<body>
<header>
  <div class="navbar">
    <ul>
      <li><a href="<?= APP_BASE ?>/php/admin/dashboard.php">Home</a></li>
      <li><a href="<?= APP_BASE ?>/php/admin/manage_shipments.php">Products</a></li>
      <li><a href="<?= APP_BASE ?>/php/admin/automation.php">Services</a></li>
      <li><a href="<?= APP_BASE ?>/php/admin/add_user.php">About</a></li>
      <li><a href="<?= APP_BASE ?>/php/admin/login.php">Contact</a></li>
    </ul>
  </div>
</header>
