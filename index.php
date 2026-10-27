<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/catalog.php';
$pageTitle = 'Good things for everyday living';
$categories = $pdo->query('SELECT id,name,slug,description FROM categories WHERE is_active=1 ORDER BY sort_order,name LIMIT 8')->fetchAll();
$products = list_products($pdo, '', null, 'featured', 8);
$heroImage = setting($pdo, 'hero_image');
require __DIR__ . '/includes/header.php';
?>
<section class="hero" style="<?= $heroImage !== '' ? 'background-image:linear-gradient(90deg,rgba(22,21,19,.74),rgba(22,21,19,.1)),url(\'' . h($heroImage) . '\')' : '' ?>">
  <div class="hero-content">
    <span class="eyebrow">Everyday, considered</span>
    <h1><?= nl2br(h(setting($pdo, 'hero_title', 'Good things for everyday living'))) ?></h1>
    <p><?= h(setting($pdo, 'hero_subtitle', 'Thoughtful finds for home, work and everything in between.')) ?></p>
    <a class="button button-light" href="products.php">Explore the collection <span aria-hidden="true">→</span></a>
  </div>
  <span class="hero-caption">Selected for life in Sri Lanka</span>
</section>
<section class="trust-strip" aria-label="Store benefits"><span>Islandwide delivery</span><i></i><span>Secure checkout</span><i></i><span>Cash on delivery available</span></section>
<section class="section-wrap category-section">
  <div class="section-heading"><div><span class="eyebrow">Shop by category</span><h2>Find your next <em>favourite.</em></h2></div><a class="text-link" href="products.php">View all products →</a></div>
  <?php if ($categories): ?>
    <div class="category-grid"><?php foreach ($categories as $category): ?><a class="category-card" href="category.php?slug=<?= rawurlencode($category['slug']) ?>"><span><?= h($category['name']) ?></span><small>Explore collection →</small></a><?php endforeach; ?></div>
  <?php else: ?><p class="empty-state">Collections are being prepared. Please visit again soon.</p><?php endif; ?>
</section>
<section class="section-wrap collections">
  <div class="section-heading"><div><span class="eyebrow">The latest edit</span><h2>Considered <em>essentials.</em></h2></div><a class="text-link" href="products.php">Shop all →</a></div>
  <?php if ($products): ?><div class="product-grid"><?php foreach ($products as $product) { product_card($product); } ?></div><?php else: ?><p class="empty-state">Our first products are on their way. Check back soon.</p><?php endif; ?>
</section>
<section class="promise">
  <div class="promise-copy"><span class="eyebrow">A little more thoughtful</span><h2>Made for the <em>everyday.</em></h2><p>More & More brings together useful, well-chosen things for your home and daily life. Browse with ease and order directly for delivery across Sri Lanka.</p><a class="text-link" href="about.php">Get to know us →</a></div>
  <div class="promise-art" role="img" aria-label="Warm neutral interior"></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
