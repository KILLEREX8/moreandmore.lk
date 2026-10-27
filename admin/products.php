<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$adminPageTitle = 'Products';
$search = trim(substr((string) ($_GET['q'] ?? ''), 0, 100));
$categoryId = filter_var($_GET['category'] ?? '', FILTER_VALIDATE_INT);
$status = (string) ($_GET['status'] ?? '');
$page = min(100000, max(1, (int) ($_GET['page'] ?? 1)));
if (($_GET['action'] ?? '') === 'new' || isset($_GET['edit'])) {
    define('ADMIN_BOOTSTRAPPED', true);
    require __DIR__ . '/product-editor.php';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        flash('error', 'Product was not found.');
        redirect('products.php');
    }
    if (($_POST['action'] ?? '') === 'toggle') {
        $update = $pdo->prepare('UPDATE products SET is_published=IF(is_published=1,0,1) WHERE id=?');
        $update->execute([$id]);
        flash('success', $update->rowCount() ? 'Product visibility updated.' : 'Product visibility is unchanged.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        try {
            $images = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id=?');
            $images->execute([$id]);
            $imagePaths = array_column($images->fetchAll(), 'image_path');
            $delete = $pdo->prepare('DELETE FROM products WHERE id=?');
            $delete->execute([$id]);
            foreach ($imagePaths as $path) delete_local_upload($path);
            flash('success', 'Product deleted.');
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $pdo->prepare('UPDATE products SET is_published=0 WHERE id=?')->execute([$id]);
            flash('success', 'This product is part of order history, so it was safely unpublished instead of deleted.');
        }
    }
    redirect('products.php');
}
$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($categoryId) {
    $where[] = 'p.category_id=?';
    $params[] = (int) $categoryId;
}
if ($status === 'published' || $status === 'hidden') {
    $where[] = 'p.is_published=?';
    $params[] = $status === 'published' ? 1 : 0;
}
$count = $pdo->prepare('SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE ' . implode(' AND ', $where));
$count->execute($params);
$productCount = (int) $count->fetchColumn();
$page = min(max(1, (int) ceil($productCount / 50)), $page);
$query = $pdo->prepare('SELECT p.*,c.name AS category_name,(SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC,id LIMIT 1) AS image_path FROM products p JOIN categories c ON c.id=p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC LIMIT 50 OFFSET ' . (($page - 1) * 50));
$query->execute($params);
$products = $query->fetchAll();
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-title"><div><h1>Products</h1><p>Create and manage catalog items. Public pages use live product data.</p></div><a class="button button-dark" href="products.php?action=new">Add product</a></div>
<form class="filter-form" method="get"><label>Search<input name="q" value="<?= h($search) ?>" placeholder="Name or SKU"></label><label>Category<select name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= h($category['name']) ?></option><?php endforeach; ?></select></label><label>Visibility<select name="status"><option value="">All</option><option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option><option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>Hidden</option></select></label><button class="button button-dark">Filter</button></form>
<section class="admin-panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Visibility</th><th>Manage</th></tr></thead><tbody><?php foreach ($products as $product): ?><tr><td><?php if ($product['image_path']): ?><img class="thumb" src="../<?= h($product['image_path']) ?>" alt=""><?php endif; ?><a href="../product.php?slug=<?= rawurlencode($product['slug']) ?>" target="_blank" rel="noopener"><?= h($product['name']) ?></a></td><td><?= h($product['sku']) ?></td><td><?= h($product['category_name']) ?></td><td><?= money(product_price($product)) ?><?php if ($product['discounted_price'] !== null): ?><br><del><?= money($product['price']) ?></del><?php endif; ?></td><td><?= (int) $product['stock_quantity'] ?></td><td><?= $product['is_published'] ? 'Published' : 'Hidden' ?></td><td><div class="admin-actions"><a class="small-button" href="products.php?edit=<?= (int) $product['id'] ?>">Edit</a><form method="post"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="small-button"><?= $product['is_published'] ? 'Hide' : 'Publish' ?></button></form><form method="post" data-confirm="Delete this product? Order history is retained."><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="action" value="delete"><button class="small-button danger-button">Delete</button></form></div></td></tr><?php endforeach; ?><?php if (!$products): ?><tr><td colspan="7">No products match this filter.</td></tr><?php endif; ?></tbody></table><?php catalog_pagination($productCount,$page,50); ?></section>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
