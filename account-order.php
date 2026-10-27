<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer-auth.php';
$customer = require_customer();
$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$orderId || $orderId < 1) {
    http_response_code(404);
    exit('Order not found.');
}

$orderQuery = $pdo->prepare(
    'SELECT id,order_number,status,total,subtotal,discount_total,delivery_charge,payment_method,created_at
     FROM orders WHERE id=? AND customer_id=? LIMIT 1'
);
$orderQuery->execute([$orderId, $customer['id']]);
$order = $orderQuery->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$itemsQuery = $pdo->prepare(
    'SELECT product_name,sku,unit_price,quantity,line_total
     FROM order_items WHERE order_id=? ORDER BY id'
);
$itemsQuery->execute([$orderId]);
$items = $itemsQuery->fetchAll();

$historyQuery = $pdo->prepare(
    'SELECT new_status,changed_at
     FROM order_status_history WHERE order_id=? ORDER BY changed_at,id'
);
$historyQuery->execute([$orderId]);
$history = $historyQuery->fetchAll();

$pageTitle = 'Order ' . $order['order_number'];
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero compact-hero"><span class="eyebrow">Order details</span><h1><?= h($order['order_number']) ?></h1><p>Placed <?= h(date('j M Y, g:i a', strtotime($order['created_at']))) ?></p></section>
<section class="section-wrap account-section"><div class="account-panel account-order-detail">
  <div class="account-order-current"><span class="eyebrow">Current status</span><h2><?= h($order['status']) ?></h2><p><?= h(customer_order_status_description($order['status'])) ?></p></div>
  <h2>Delivery progress</h2>
  <?php if ($history): ?><ol class="status-timeline account-status-timeline"><?php foreach ($history as $event): ?><li><strong><?= h($event['new_status']) ?></strong><time><?= h(date('j M Y, g:i a', strtotime($event['changed_at']))) ?></time></li><?php endforeach; ?></ol><?php else: ?><p>Order updates are not available yet.</p><?php endif; ?>
  <h2>Items</h2>
  <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><?= h($item['product_name']) ?></td><td><?= h($item['sku']) ?></td><td><?= money($item['unit_price']) ?></td><td><?= (int) $item['quantity'] ?></td><td><?= money($item['line_total']) ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="4">Subtotal</th><td><?= money($order['subtotal']) ?></td></tr><tr><th colspan="4">Discount</th><td><?= money($order['discount_total']) ?></td></tr><tr><th colspan="4">Delivery</th><td><?= money($order['delivery_charge']) ?></td></tr><tr><th colspan="4">Total</th><td><strong><?= money($order['total']) ?></strong></td></tr></tfoot></table></div>
  <p>Payment method: <?= h(strtoupper($order['payment_method'])) ?></p>
  <p><a class="button button-dark" href="account.php">Back to my orders</a></p>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
