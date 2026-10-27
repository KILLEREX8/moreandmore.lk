<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/catalog.php';
$sort = (string) ($_GET['sort'] ?? 'featured');
$categoryId = filter_var($_GET['category'] ?? null, FILTER_VALIDATE_INT);
$categoryId = $categoryId !== false && $categoryId !== null && $categoryId > 0 ? $categoryId : null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageTitle = 'All products';
$totalProducts = count_products($pdo, '', $categoryId);
$page = min(max(1, (int) ceil($totalProducts / 24)), $page);
$products = list_products($pdo, '', $categoryId, $sort, 24, $page);
$categoryOptions = $pdo->query('SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order,name')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><span class="eyebrow">The collection</span><h1>All products</h1><p>Discover considered finds for home, work and everything in between.</p></section>
<section class="section-wrap listing-section">
  <div class="listing-toolbar"><span><?= $totalProducts ?> products</span><form method="get"><label for="category">Category</label><select id="category" name="category" data-submit-on-change><option value="">All categories</option><?php foreach ($categoryOptions as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= h($category['name']) ?></option><?php endforeach; ?></select><label for="sort">Sort by</label><select id="sort" name="sort" data-submit-on-change><option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured</option><option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option><option value="price-low" <?= $sort === 'price-low' ? 'selected' : '' ?>>Price: low to high</option><option value="price-high" <?= $sort === 'price-high' ? 'selected' : '' ?>>Price: high to low</option><option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option></select></form></div>
  <?php if ($products): ?><div class="product-grid"><?php foreach ($products as $product) { product_card($product); } ?></div><?php catalog_pagination($totalProducts, $page); ?><?php else: ?><p class="empty-state">No products are available yet.</p><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
