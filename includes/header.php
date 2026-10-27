<?php
require_once __DIR__ . '/customer-auth.php';
$currentCustomer = current_customer();
$siteName = setting($pdo, 'site_name', 'More & More');
$pageTitle = $pageTitle ?? $siteName;
$activeCategories = $pdo->query('SELECT id, name, slug FROM categories WHERE is_active=1 ORDER BY sort_order, name LIMIT 8')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#201f1d">
  <title><?= h($pageTitle) ?> | <?= h($siteName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/contact-widget.css">
  <link rel="stylesheet" href="assets/css/order-status.css">
</head>
<body>
<div class="announcement"><?= h(setting($pdo, 'promo_message', 'Thoughtful finds for a more beautiful everyday')) ?></div>
<header class="site-header">
  <a class="brand" href="index.php" aria-label="More & More home"><?php if (setting($pdo, 'site_logo')): ?><img class="site-logo" src="<?= h(setting($pdo, 'site_logo')) ?>" alt=""><?php endif; ?><?= h($siteName) ?></a>
  <button class="menu-toggle" type="button" aria-expanded="false" aria-label="Toggle navigation">☰</button>
  <nav class="main-nav" aria-label="Main navigation">
    <a href="index.php">Home</a><a href="products.php">Shop all</a>
    <?php foreach (array_slice($activeCategories, 0, 4) as $category): ?>
      <a href="category.php?slug=<?= rawurlencode($category['slug']) ?>"><?= h($category['name']) ?></a>
    <?php endforeach; ?>
    <a href="about.php">Our story</a>
  </nav>
  <div class="header-actions">
    <form class="search-form" action="search.php" method="get" role="search">
      <label class="sr-only" for="site-search">Search products</label>
      <input id="site-search" type="search" name="q" placeholder="Search" value="<?= h($_GET['q'] ?? '') ?>">
      <button aria-label="Submit search">⌕</button>
    </form>
    <a class="cart-link" href="cart.php">Bag <span><?= cart_count() ?></span></a>
    <?php if ($currentCustomer): ?>
      <a class="account-link" href="account.php"><?= h($currentCustomer['username']) ?></a>
      <form class="account-logout-form" action="account-logout.php" method="post"><?= csrf_field() ?><button class="text-link">Sign out</button></form>
    <?php else: ?>
      <a class="account-link" href="account-login.php">Sign in</a>
    <?php endif; ?>
  </div>
</header>
<?php foreach (take_flashes() as $notice): ?>
  <div class="notice notice-<?= h($notice['type']) ?>" role="status"><?= h($notice['message']) ?></div>
<?php endforeach; ?>
<main>
