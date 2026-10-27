<?php
$adminPageTitle = $adminPageTitle ?? 'Dashboard';
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= h($adminPageTitle) ?> · More & More Admin</title><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/admin.css"></head>
<body class="admin-body">
<header class="admin-topbar"><a class="brand" href="index.php">More & More <span>Admin</span></a><div><span><?= h($_SESSION['admin_username'] ?? 'Administrator') ?></span><form action="logout.php" method="post"><?= csrf_field() ?><button>Sign out</button></form></div></header>
<nav class="admin-nav" aria-label="Administration"><a href="index.php">Overview</a><a href="products.php">Products</a><a href="categories.php">Categories</a><a href="orders.php">Orders</a><a href="customers.php">Customers</a><a href="messages.php">Messages</a><a href="reports.php">Reports</a><a href="settings.php">Store settings</a><a href="change-password.php">Change password</a><a href="../index.php" target="_blank" rel="noopener">View store ↗</a></nav>
<?php foreach (take_flashes() as $notice): ?><div class="notice notice-<?= h($notice['type']) ?>"><?= h($notice['message']) ?></div><?php endforeach; ?>
<main class="admin-main">
