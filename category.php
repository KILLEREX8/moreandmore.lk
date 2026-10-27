<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/catalog.php';
$slug = trim((string) ($_GET['slug'] ?? ''));
$statement = $pdo->prepare('SELECT id,name,description FROM categories WHERE slug=? AND is_active=1');
$statement->execute([$slug]);
$category = $statement->fetch();
if (!$category) {
    http_response_code(404);
}
$pageTitle = $category['name'] ?? 'Collection not found';
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalProducts = $category ? count_products($pdo, '', (int) $category['id']) : 0;
$page = min(max(1, (int) ceil($totalProducts / 24)), $page);
$products = $category ? list_products($pdo, '', (int) $category['id'], (string) ($_GET['sort'] ?? 'featured'), 24, $page) : [];
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><span class="eyebrow">Browse collection</span><h1><?= h($pageTitle) ?></h1><?php if ($category['description'] ?? ''): ?><p><?= h($category['description']) ?></p><?php endif; ?></section>
<section class="section-wrap listing-section"><?php if ($category && $products): ?><div class="product-grid"><?php foreach ($products as $product) { product_card($product); } ?></div><?php catalog_pagination($totalProducts, $page); ?><?php elseif ($category): ?><p class="empty-state">There are no products in this collection just yet.</p><?php else: ?><p class="empty-state">That collection could not be found. <a href="products.php">Browse all products</a>.</p><?php endif; ?></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
