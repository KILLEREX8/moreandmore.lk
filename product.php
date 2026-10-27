<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/catalog.php';
$query = $pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug,
  (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image_path
  FROM products p JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.is_published=1 AND c.is_active=1");
$query->execute([trim((string) ($_GET['slug'] ?? ''))]);
$product = $query->fetch();
if (!$product) {
    http_response_code(404);
}
$gallery = [];
if ($product) {
    $galleryQuery = $pdo->prepare('SELECT image_path,alt_text FROM product_images WHERE product_id=? ORDER BY is_primary DESC,sort_order,id');
    $galleryQuery->execute([(int) $product['id']]);
    $gallery = $galleryQuery->fetchAll();
}
$pageTitle = $product['name'] ?? 'Product not found';
$related = $product ? list_products($pdo, '', (int) $product['category_id'], 'featured', 5) : [];
$related = array_values(array_filter($related, static fn (array $item): bool => (int) $item['id'] !== (int) $product['id']));
$specifications = [];
if (!empty($product['specifications'])) {
    $specifications = json_decode((string) $product['specifications'], true) ?: [];
}
require __DIR__ . '/includes/header.php';
?>
<section class="section-wrap product-detail">
<?php if (!$product): ?><div class="empty-state"><h1>Product not found</h1><a href="products.php">Continue shopping</a></div><?php else: ?>
  <nav class="breadcrumbs"><a href="index.php">Home</a> / <a href="category.php?slug=<?= rawurlencode($product['category_slug']) ?>"><?= h($product['category_name']) ?></a> / <?= h($product['name']) ?></nav>
  <div class="product-detail-grid"><div class="detail-gallery"><div class="detail-photo"><?php if ($product['image_path']): ?><img id="detail-image" src="<?= h($product['image_path']) ?>" alt="<?= h($product['name']) ?>"><?php else: ?><div class="product-placeholder detail-placeholder"><?= h(mb_strtoupper(mb_substr($product['name'], 0, 1))) ?></div><?php endif; ?></div><?php if (count($gallery)>1): ?><div class="product-thumbnails"><?php foreach ($gallery as $index => $image): ?><button type="button" class="thumbnail-button <?= $index===0?'is-selected':'' ?>" data-image="<?= h($image['image_path']) ?>" data-alt="<?= h($image['alt_text'] ?: $product['name']) ?>" aria-label="View product image <?= $index+1 ?>"><img src="<?= h($image['image_path']) ?>" alt=""></button><?php endforeach; ?></div><?php endif; ?></div>
  <div class="detail-copy"><span class="eyebrow"><?= h($product['category_name']) ?></span><h1><?= h($product['name']) ?></h1><div class="detail-price"><?= money(product_price($product)) ?><?php if ($product['discounted_price'] !== null): ?><del><?= money($product['price']) ?></del><?php endif; ?></div><p><?= nl2br(h($product['description'])) ?></p><p class="<?= (int) $product['stock_quantity'] > 0 ? 'stock-available' : 'stock-note' ?>"><?= (int) $product['stock_quantity'] > 0 ? 'In stock · ' . (int) $product['stock_quantity'] . ' available' : 'Currently out of stock' ?></p>
    <?php if ((int) $product['stock_quantity'] > 0): ?><form action="cart.php" method="post" class="add-cart-form"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><label for="quantity">Quantity</label><input id="quantity" type="number" name="quantity" min="1" max="<?= min(99, (int) $product['stock_quantity']) ?>" value="1" required><button class="button button-dark">Add to bag</button></form><?php endif; ?>
    <?php if ($specifications): ?><div class="spec-list"><h2>Details</h2><?php foreach ($specifications as $label => $value): ?><div><strong><?= h($label) ?></strong><span><?= h($value) ?></span></div><?php endforeach; ?></div><?php endif; ?>
  </div></div>
<?php endif; ?></section>
<?php if ($related): ?><section class="section-wrap collections"><div class="section-heading"><h2>You may also <em>like.</em></h2></div><div class="product-grid"><?php foreach (array_slice($related, 0, 4) as $item) { product_card($item); } ?></div></section><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
