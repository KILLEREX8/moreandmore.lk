<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer-auth.php';
$customer = require_customer();
$pageTitle = 'My account';
$query = $pdo->prepare('SELECT o.id,o.order_number,o.status,o.total,o.created_at,COUNT(oi.id) AS item_count FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id WHERE o.customer_id=? GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50');
$query->execute([$customer['id']]);
$orders = $query->fetchAll();
$statusHistory = [];
if ($orders) {
    $orderIds = array_map(static fn (array $order): int => (int) $order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $historyQuery = $pdo->prepare(
        "SELECT order_id,new_status,change_note,changed_at
         FROM order_status_history
         WHERE order_id IN ({$placeholders})
         ORDER BY order_id,changed_at,id"
    );
    $historyQuery->execute($orderIds);
    foreach ($historyQuery->fetchAll() as $event) {
        $statusHistory[(int) $event['order_id']][] = $event;
    }
}
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero compact-hero"><span class="eyebrow">Customer account</span><h1>Welcome, <?= h($customer['username']) ?>.</h1><p>See your order status and delivery progress below. Guest orders can be checked using their private tracking code.</p></section>
<section class="section-wrap account-section"><div class="account-panel">
<div class="section-heading"><div><span class="eyebrow">Order history and delivery updates</span><h2>Your orders</h2></div></div>
<?php if ($orders): ?><div class="account-orders"><?php foreach ($orders as $order): ?><article class="account-order"><div><strong><?= h($order['order_number']) ?></strong><span><?= h(date('j M Y', strtotime($order['created_at']))) ?></span></div><div><span><?= h($order['status']) ?> · <?= (int) $order['item_count'] ?> item(s)</span><strong><?= money((int) $order['total']) ?></strong></div><p class="account-order-progress"><strong><?= h(customer_order_status_description($order['status'])) ?></strong></p><?php if (!empty($statusHistory[(int) $order['id']])): ?><ol class="status-timeline account-status-timeline"><?php foreach ($statusHistory[(int) $order['id']] as $event): ?><li><strong><?= h($event['new_status']) ?></strong><time><?= h(date('j M Y, g:i a', strtotime($event['changed_at']))) ?></time><?php if ($event['change_note']): ?><span><?= h($event['change_note']) ?></span><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?><a class="button button-dark account-order-button" href="account-order.php?id=<?= (int) $order['id'] ?>">View order and delivery progress</a></article><?php endforeach; ?></div><?php else: ?><p>You have not placed any orders while signed in.</p><?php endif; ?>
<p><a class="text-link" href="products.php">Continue shopping</a> · <a class="text-link" href="track-order.php">Track an order</a></p>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
