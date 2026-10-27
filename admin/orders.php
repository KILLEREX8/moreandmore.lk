<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/catalog.php';
require_admin();
$adminPageTitle = 'Orders';
$term = trim(substr((string) ($_GET['q'] ?? ''), 0, 100));
$status = (string) ($_GET['status'] ?? '');
$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');
$page = min(100000, max(1, (int) ($_GET['page'] ?? 1)));
$where = ['1=1'];
$params = [];
if ($term !== '') {
    $where[] = '(order_number LIKE ? OR customer_phone LIKE ? OR customer_name LIKE ?)';
    foreach ([$term,$term,$term] as $value) $params[] = '%' . $value . '%';
}
if (in_array($status, order_statuses(), true)) {
    $where[] = 'status=?';
    $params[] = $status;
}
if (valid_date($from)) {
    $where[] = 'created_at>=?';
    $params[] = $from . ' 00:00:00';
} elseif ($from !== '') $from = '';
if (valid_date($to)) {
    $where[] = 'created_at<=?';
    $params[] = $to . ' 23:59:59';
} elseif ($to !== '') $to = '';
$count = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE ' . implode(' AND ', $where));
$count->execute($params);
$orderCount = (int) $count->fetchColumn();
$page = min(max(1, (int) ceil($orderCount / 50)), $page);
$query = $pdo->prepare('SELECT id,order_number,customer_name,customer_phone,district,total,status,payment_status,created_at FROM orders WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 50 OFFSET ' . (($page - 1) * 50));
$query->execute($params);
$orders = $query->fetchAll();
require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-title"><div><h1>Orders</h1><p>Search every order by order number or customer phone; results are paginated.</p></div><a class="button button-dark" href="reports.php?export=orders">Export CSV</a></div>
<form class="filter-form" method="get"><label>Search<input name="q" value="<?= h($term) ?>" placeholder="Order, phone or customer"></label><label>Status<select name="status"><option value="">All statuses</option><?php foreach (order_statuses() as $item): ?><option <?= $status === $item ? 'selected' : '' ?>><?= h($item) ?></option><?php endforeach; ?></select></label><label>From<input type="date" name="from" value="<?= h($from) ?>"></label><label>To<input type="date" name="to" value="<?= h($to) ?>"></label><button class="button button-dark">Apply</button></form>
<section class="admin-panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Customer</th><th>District</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td><a href="order-details.php?id=<?= (int) $order['id'] ?>"><?= h($order['order_number']) ?></a></td><td><?= h($order['customer_name']) ?><br><a href="tel:<?= h($order['customer_phone']) ?>"><?= h($order['customer_phone']) ?></a></td><td><?= h($order['district']) ?></td><td><?= money($order['total']) ?></td><td><?= h($order['payment_status']) ?></td><td><span class="status-pill status-<?= h($order['status']) ?>"><?= h($order['status']) ?></span></td><td><?= h(date('j M Y, g:i a', strtotime($order['created_at']))) ?></td></tr><?php endforeach; ?><?php if (!$orders): ?><tr><td colspan="7">No orders match the selected filters.</td></tr><?php endif; ?></tbody></table><?php catalog_pagination($orderCount,$page,50); ?></section>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
