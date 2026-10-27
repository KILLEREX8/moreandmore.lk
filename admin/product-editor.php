<?php
if (!defined('ADMIN_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$productId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$product = null;
$images = [];
if ($productId) {
    $query = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $query->execute([$productId]);
    $product = $query->fetch();
    if (!$product) {
        flash('error', 'Product not found.');
        redirect('products.php');
    }
    $imageQuery = $pdo->prepare('SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC,sort_order,id');
    $imageQuery->execute([$productId]);
    $images = $imageQuery->fetchAll();
}
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY is_active DESC,sort_order,name')->fetchAll();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $sku = strtoupper(trim((string) ($_POST['sku'] ?? '')));
    $category = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_INT);
    $discount = trim((string) ($_POST['discounted_price'] ?? ''));
    $stock = filter_var($_POST['stock_quantity'] ?? null, FILTER_VALIDATE_INT);
    $discountValue = $discount === '' ? null : filter_var($discount, FILTER_VALIDATE_INT);
    if (strlen($name) < 2 || strlen($name) > 180) $errors[] = 'Product name must be 2–180 characters.';
    if (strlen($description) > 20000) $errors[] = 'Description is too long.';
    if (!preg_match('/^[A-Z0-9._-]{2,80}$/', $sku)) $errors[] = 'SKU must use 2–80 letters, numbers, dots, underscores or hyphens.';
    $categoryCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id=?');
    $categoryCheck->execute([(int) $category]);
    if (!$category || (int) $categoryCheck->fetchColumn() !== 1) $errors[] = 'Choose a valid category.';
    if ($price === false || $price < 1 || $price > 100000000) $errors[] = 'Enter a valid product price in LKR.';
    if ($discount !== '' && ($discountValue === false || $discountValue < 1 || $discountValue >= $price)) $errors[] = 'Discount price must be positive and lower than the regular price.';
    if ($stock === false || $stock < 0 || $stock > 1000000) $errors[] = 'Stock must be between 0 and 1,000,000.';
    $specifications = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) ($_POST['specifications'] ?? '')) ?: [] as $line) {
        if (trim($line) === '') continue;
        if (!str_contains($line, ':')) {
            $errors[] = 'Enter specifications one per line in “Name: value” format.';
            break;
        }
        [$label, $value] = array_map('trim', explode(':', $line, 2));
        if ($label === '' || $value === '' || strlen($label) > 80 || strlen($value) > 250) {
            $errors[] = 'Specification labels and values must be valid and under 250 characters.';
            break;
        }
        $specifications[$label] = $value;
    }
    $slugBase = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
    if ($slugBase === '') $errors[] = 'Product name must include a letter or number.';
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            if ($product) {
                $save = $pdo->prepare('UPDATE products SET category_id=?,name=?,sku=?,description=?,specifications=?,price=?,discounted_price=?,stock_quantity=?,is_featured=?,is_published=? WHERE id=?');
                $save->execute([(int) $category,$name,$sku,$description,json_encode($specifications, JSON_UNESCAPED_UNICODE),$price,$discountValue,$stock,isset($_POST['is_featured']) ? 1 : 0,isset($_POST['is_published']) ? 1 : 0,$productId]);
            } else {
                $slug = $slugBase . '-' . bin2hex(random_bytes(3));
                $save = $pdo->prepare('INSERT INTO products (category_id,name,slug,sku,description,specifications,price,discounted_price,stock_quantity,is_featured,is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $save->execute([(int) $category,$name,$slug,$sku,$description,json_encode($specifications, JSON_UNESCAPED_UNICODE),$price,$discountValue,$stock,isset($_POST['is_featured']) ? 1 : 0,isset($_POST['is_published']) ? 1 : 0]);
                $productId = (int) $pdo->lastInsertId();
            }
            $files = $_FILES['images'] ?? null;
            $uploadedPaths = [];
            if ($files && is_array($files['name'] ?? null)) {
                $count = count($files['name']);
                if ($count > 10) throw new RuntimeException('Upload no more than 10 images at once.');
                $existingImageCount = (int) $pdo->query('SELECT COUNT(*) FROM product_images WHERE product_id=' . (int) $productId)->fetchColumn();
                for ($i = 0; $i < $count; $i++) {
                    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                    $uploaded = upload_product_image(['name'=>$files['name'][$i],'type'=>$files['type'][$i],'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]]);
                    if ($uploaded) {
                        $uploadedPaths[] = $uploaded;
                        $primary = $existingImageCount === 0 && count($uploadedPaths) === 1;
                        $pdo->prepare('INSERT INTO product_images (product_id,image_path,alt_text,is_primary,sort_order) VALUES (?,?,?,?,?)')->execute([$productId,$uploaded,$name,$primary ? 1 : 0,$existingImageCount + count($uploadedPaths)]);
                    }
                }
            }
            $selectedImage = filter_var($_POST['primary_image'] ?? null, FILTER_VALIDATE_INT);
            if ($selectedImage) {
                $pdo->prepare('UPDATE product_images SET is_primary=0 WHERE product_id=?')->execute([$productId]);
                $pdo->prepare('UPDATE product_images SET is_primary=1 WHERE product_id=? AND id=?')->execute([$productId,$selectedImage]);
            }
            $pdo->commit();
            flash('success', $product ? 'Product updated.' : 'Product created.');
            redirect('products.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($uploadedPaths ?? [] as $path) {
                $file = dirname(__DIR__) . '/' . $path;
                if (is_file($file)) unlink($file);
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $errors[] = 'This SKU is already in use.';
            } else {
                error_log('Product save failed: ' . $exception->getMessage());
                $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The product could not be saved.';
            }
        }
    }
}
$adminPageTitle = $product ? 'Edit product' : 'Add product';
require dirname(__DIR__) . '/includes/admin-header.php';
$specText = '';
if (!empty($product['specifications'])) {
    foreach (json_decode((string) $product['specifications'], true) ?: [] as $label => $value) $specText .= $label . ': ' . $value . "\n";
}
?>
<div class="admin-title"><div><h1><?= $product ? 'Edit product' : 'Add product' ?></h1><p>Price and inventory are validated and saved on the server.</p></div><a class="text-link" href="products.php">← Back to products</a></div>
<?php if ($errors): ?><div class="notice notice-error"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>
<section class="admin-panel"><form method="post" enctype="multipart/form-data" class="admin-form" data-disable-on-submit><?= csrf_field() ?>
<label class="wide">Product name<input name="name" maxlength="180" value="<?= h($_POST['name'] ?? $product['name'] ?? '') ?>" required></label>
<label>SKU<input name="sku" maxlength="80" value="<?= h($_POST['sku'] ?? $product['sku'] ?? '') ?>" required></label>
<label>Category<select name="category_id" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($_POST['category_id'] ?? $product['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= h($category['name']) ?></option><?php endforeach; ?></select></label>
<label>Price (LKR)<input type="number" min="1" max="100000000" step="1" name="price" value="<?= h($_POST['price'] ?? $product['price'] ?? '') ?>" required></label>
<label>Discounted price (LKR)<input type="number" min="1" name="discounted_price" value="<?= h($_POST['discounted_price'] ?? $product['discounted_price'] ?? '') ?>"><small class="spec-help">Leave empty if not discounted. Must be less than the regular price.</small></label>
<label>Stock quantity<input type="number" min="0" max="1000000" name="stock_quantity" value="<?= h($_POST['stock_quantity'] ?? $product['stock_quantity'] ?? '0') ?>" required></label>
<label class="wide">Description<textarea name="description" rows="5" maxlength="20000"><?= h($_POST['description'] ?? $product['description'] ?? '') ?></textarea></label>
<label class="wide">Specifications<textarea name="specifications" rows="5" placeholder="Material: Stainless steel&#10;Warranty: 1 year"><?= h($_POST['specifications'] ?? $specText) ?></textarea><small class="spec-help">One specification per line: Name: value</small></label>
<label class="wide">Product images<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><small class="spec-help">JPG, PNG or WebP; up to 5 MB each, no more than 10 per save.</small></label>
<?php if ($images): ?><div class="wide"><div class="image-list"><?php foreach ($images as $image): ?><label><img src="../<?= h($image['image_path']) ?>" alt="<?= h($image['alt_text']) ?>"><span><input type="radio" name="primary_image" value="<?= (int) $image['id'] ?>" <?= $image['is_primary'] ? 'checked' : '' ?>> Main image</span></label><?php endforeach; ?></div></div><?php endif; ?>
<label class="checkbox-label"><input type="checkbox" name="is_featured" value="1" <?= !empty($_POST) ? (isset($_POST['is_featured']) ? 'checked' : '') : (!empty($product['is_featured']) ? 'checked' : '') ?>> Featured</label>
<label class="checkbox-label"><input type="checkbox" name="is_published" value="1" <?= !empty($_POST) ? (isset($_POST['is_published']) ? 'checked' : '') : (!isset($product) || !empty($product['is_published']) ? 'checked' : '') ?>> Published on store</label>
<button class="button button-dark"><?= $product ? 'Save changes' : 'Create product' ?></button></form></section>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
