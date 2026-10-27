<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$adminPageTitle = 'Overview';
$stats = [
    'Total products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'Total orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'Pending orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Pending'")->fetchColumn(),
    'Confirmed orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Confirmed'")->fetchColumn(),
    'Processing orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Processing'")->fetchColumn(),
    'Shipped orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Shipped'")->fetchColumn(),
    'Delivered orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Delivered'")->fetchColumn(),
    'Cancelled orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Cancelled'")->fetchColumn(),
    'Customers' => (int) $pdo->query('SELECT (SELECT COUNT(*) FROM customers) + (SELECT COUNT(DISTINCT customer_phone) FROM orders WHERE customer_id IS NULL)')->fetchColumn(),
    'Paid sales' => (int) $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status='paid' AND status='Delivered'")->fetchColumn(),
];
$lowStock = $pdo->prepare('SELECT p.id,p.name,p.stock_quantity,c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.stock_quantity<=? ORDER BY p.stock_quantity,p.name LIMIT 10');
$lowStock->execute([max(0, (int) setting($pdo, 'low_stock_threshold', '5'))]);
$recent = $pdo->query('SELECT id,order_number,customer_name,customer_phone,total,status,payment_status,created_at FROM orders ORDER BY created_at DESC LIMIT 10')->fetchAll();
require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-title"><div><h1>Store overview</h1><p>Live store activity and sales from your database.</p></div><a class="button button-dark" href="products.php?action=new">Add product</a></div>
<div class="stats-grid"><?php foreach ($stats as $label => $value): ?><article class="stat-card"><span><?= h($label) ?></span><strong><?= $label === 'Paid sales' ? money($value) : number_format($value) ?></strong></article><?php endforeach; ?></div>
<div class="admin-grid"><section class="admin-panel"><div class="admin-title"><h2>Recent orders</h2><a class="text-link" href="orders.php">View all →</a></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($recent as $order): ?><tr><td><a href="order-details.php?id=<?= (int) $order['id'] ?>"><?= h($order['order_number']) ?></a></td><td><?= h($order['customer_name']) ?><br><?= h($order['customer_phone']) ?></td><td><?= money($order['total']) ?><br><small><?= h($order['payment_status']) ?></small></td><td><span class="status-pill status-<?= h($order['status']) ?>"><?= h($order['status']) ?></span></td><td><?= h(date('j M Y', strtotime($order['created_at']))) ?></td></tr><?php endforeach; ?><?php if (!$recent): ?><tr><td colspan="5">No orders have been placed yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="admin-panel"><h2>Low stock</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Product</th><th>Category</th><th>Stock</th></tr></thead><tbody><?php foreach ($lowStock->fetchAll() as $item): ?><tr><td><a href="products.php?q=<?= rawurlencode($item['name']) ?>"><?= h($item['name']) ?></a></td><td><?= h($item['category_name']) ?></td><td><?= (int) $item['stock_quantity'] ?></td></tr><?php endforeach; ?></tbody></table></div><p><a class="text-link" href="reports.php">View inventory report →</a></p></section></div>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
