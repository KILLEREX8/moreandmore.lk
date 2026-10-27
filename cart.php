<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        flash('success', 'Your bag has been emptied.');
        redirect('cart.php');
    }
    if ($action === 'remove' && $id) {
        unset($_SESSION['cart'][$id]);
        flash('success', 'The item was removed from your bag.');
        redirect('cart.php');
    }
    if (($action === 'add' || $action === 'update') && $id) {
        $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
        $statement = $pdo->prepare('SELECT id,stock_quantity FROM products WHERE id=? AND is_published=1');
        $statement->execute([$id]);
        $product = $statement->fetch();
        if (!$product || $quantity === false || $quantity < 1 || $quantity > 99 || $quantity > (int) $product['stock_quantity']) {
            flash('error', 'That quantity is unavailable. Please check the current stock.');
            redirect('cart.php');
        }
        $_SESSION['cart'] ??= [];
        $_SESSION['cart'][$id] = $action === 'add'
            ? min((int) $product['stock_quantity'], (int) ($_SESSION['cart'][$id] ?? 0) + $quantity)
            : $quantity;
        flash('success', 'Your bag has been updated.');
        redirect('cart.php');
    }
    flash('error', 'That cart action could not be completed.');
    redirect('cart.php');
}
$pageTitle = 'Shopping bag';
$items = cart_items($pdo);
$subtotal = array_sum(array_map(static fn (array $item): int => product_price($item) * (int) $item['quantity'], $items));
$savings = array_sum(array_map(static fn (array $item): int => max(0, (int) $item['price'] - product_price($item)) * (int) $item['quantity'], $items));
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero compact-hero"><span class="eyebrow">Your selection</span><h1>Shopping bag</h1></section>
<section class="section-wrap cart-section">
<?php if (!$items): ?><div class="empty-state"><h2>Your bag is waiting.</h2><p>Explore the collection and add something lovely.</p><a class="button button-dark" href="products.php">Continue shopping</a></div>
<?php else: ?><div class="cart-layout"><div class="cart-lines"><?php foreach ($items as $item): ?><article class="cart-line">
  <?php if ($item['image_path']): ?><img src="<?= h($item['image_path']) ?>" alt="<?= h($item['name']) ?>"><?php else: ?><div class="product-placeholder cart-placeholder"><?= h(mb_strtoupper(mb_substr($item['name'], 0, 1))) ?></div><?php endif; ?>
  <div class="cart-line-info"><a href="product.php?slug=<?= rawurlencode($item['slug']) ?>"><?= h($item['name']) ?></a><span><?= money(product_price($item)) ?> each</span><span><?= (int) $item['stock_quantity'] ?> in stock</span></div>
  <form method="post" class="quantity-form"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><label class="sr-only" for="qty-<?= (int) $item['id'] ?>">Quantity</label><input id="qty-<?= (int) $item['id'] ?>" type="number" name="quantity" min="1" max="<?= min(99, (int) $item['stock_quantity']) ?>" value="<?= (int) $item['quantity'] ?>"><button class="text-link">Update</button></form>
  <strong><?= money(product_price($item) * (int) $item['quantity']) ?></strong>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><button class="remove-link" aria-label="Remove <?= h($item['name']) ?>">Remove</button></form>
</article><?php endforeach; ?></div>
  <aside class="cart-summary"><h2>Order summary</h2><div><span>Subtotal</span><strong><?= money($subtotal + $savings) ?></strong></div><?php if ($savings): ?><div><span>Discount</span><strong>−<?= money($savings) ?></strong></div><?php endif; ?><div><span>Items total</span><strong><?= money($subtotal) ?></strong></div><p>Delivery charges, if applicable, are calculated at checkout.</p><a class="button button-dark button-full" href="checkout.php">Proceed to checkout</a><a class="text-link" href="products.php">Continue shopping</a></aside></div>
<?php endif; ?></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
