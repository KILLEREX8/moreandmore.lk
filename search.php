<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/catalog.php';
$term = trim(substr((string) ($_GET['q'] ?? ''), 0, 100));
$pageTitle = 'Search results';
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalProducts = $term === '' ? 0 : count_products($pdo, $term);
$page = min(max(1, (int) ceil($totalProducts / 24)), $page);
$products = $term === '' ? [] : list_products($pdo, $term, null, (string) ($_GET['sort'] ?? 'featured'), 24, $page);
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><span class="eyebrow">Find something lovely</span><h1>Search</h1><form class="large-search" action="search.php" method="get"><label class="sr-only" for="search-term">Search products</label><input id="search-term" type="search" name="q" value="<?= h($term) ?>" placeholder="What are you looking for?"><button class="button button-dark">Search</button></form></section>
<section class="section-wrap listing-section"><?php if ($term !== ''): ?><p class="results-label"><?= $totalProducts ?> results for “<?= h($term) ?>”</p><?php endif; ?><?php if ($products): ?><div class="product-grid"><?php foreach ($products as $product) { product_card($product); } ?></div><?php catalog_pagination($totalProducts, $page); ?><?php elseif ($term !== ''): ?><p class="empty-state">No products matched that search. Try another word.</p><?php endif; ?></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
