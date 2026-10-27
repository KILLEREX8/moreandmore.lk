<?php
declare(strict_types=1);

function list_products(PDO $pdo, string $search = '', ?int $categoryId = null, string $sort = 'featured', int $limit = 24, int $page = 1): array
{
    $where = ['p.is_published=1', 'c.is_active=1'];
    $values = [];
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)';
        $term = '%' . $search . '%';
        array_push($values, $term, $term, $term);
    }
    if ($categoryId !== null) {
        $where[] = 'p.category_id=?';
        $values[] = $categoryId;
    }
    $ordering = match ($sort) {
        'price-low' => 'COALESCE(p.discounted_price,p.price) ASC, p.id DESC',
        'price-high' => 'COALESCE(p.discounted_price,p.price) DESC, p.id DESC',
        'newest' => 'p.created_at DESC, p.id DESC',
        'name' => 'p.name ASC',
        default => 'p.is_featured DESC, p.created_at DESC',
    };
    $limit = min(60, max(1, $limit));
    $page = min(100000, max(1, $page));
    $offset = ($page - 1) * $limit;
    $sql = 'SELECT p.*, c.name AS category_name,
        (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image_path
        FROM products p JOIN categories c ON c.id=p.category_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $ordering . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $query = $pdo->prepare($sql);
    $query->execute($values);
    return $query->fetchAll();
}

function count_products(PDO $pdo, string $search = '', ?int $categoryId = null): int
{
    $where = ['p.is_published=1', 'c.is_active=1'];
    $values = [];
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)';
        $term = '%' . $search . '%';
        array_push($values, $term, $term, $term);
    }
    if ($categoryId !== null) {
        $where[] = 'p.category_id=?';
        $values[] = $categoryId;
    }
    $query = $pdo->prepare('SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE ' . implode(' AND ', $where));
    $query->execute($values);
    return (int) $query->fetchColumn();
}

function catalog_pagination(int $total, int $page, int $perPage = 24, string $parameter = 'page'): void
{
    $pages = (int) ceil($total / $perPage);
    if ($pages < 2) return;
    $page = min($pages, max(1, $page));
    $parameters = $_GET;
    unset($parameters[$parameter]);
    ?>
    <nav class="pagination" aria-label="Product pages">
      <?php if ($page > 1): $parameters[$parameter] = $page - 1; ?><a href="<?= h(basename($_SERVER['SCRIPT_NAME']) . '?' . http_build_query($parameters)) ?>">← Previous</a><?php endif; ?>
      <span>Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): $parameters[$parameter] = $page + 1; ?><a href="<?= h(basename($_SERVER['SCRIPT_NAME']) . '?' . http_build_query($parameters)) ?>">Next →</a><?php endif; ?>
    </nav>
    <?php
}

function product_card(array $product): void
{
    $price = product_price($product);
    ?>
    <article class="product-card">
      <a class="product-photo" href="product.php?slug=<?= rawurlencode($product['slug']) ?>">
        <?php if (!empty($product['image_path'])): ?>
          <img src="<?= h($product['image_path']) ?>" alt="<?= h($product['name']) ?>" loading="lazy">
        <?php else: ?>
          <span class="product-placeholder" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($product['name'], 0, 1))) ?></span>
        <?php endif; ?>
        <?php if (!empty($product['discounted_price'])): ?><span class="product-badge">Special price</span><?php endif; ?>
      </a>
      <div class="product-info">
        <span class="product-category"><?= h($product['category_name'] ?? '') ?></span>
        <a class="product-name" href="product.php?slug=<?= rawurlencode($product['slug']) ?>"><?= h($product['name']) ?></a>
        <div class="product-price"><?= money($price) ?><?php if (!empty($product['discounted_price'])): ?><del><?= money($product['price']) ?></del><?php endif; ?></div>
        <?php if ((int) $product['stock_quantity'] < 1): ?><span class="stock-note">Out of stock</span><?php endif; ?>
      </div>
    </article>
    <?php
}
