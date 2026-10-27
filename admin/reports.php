<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/catalog.php';
require_admin();
$adminPageTitle = 'Reports';
$export = (string) ($_GET['export'] ?? '');
$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));
if (!valid_date($from)) $from = date('Y-m-01');
if (!valid_date($to)) $to = date('Y-m-d');
$start = $from . ' 00:00:00';
$end = $to . ' 23:59:59';
if ($export === 'orders' || $export === 'sales') {
    $salesFilter = $export === 'sales' ? " AND payment_status='paid' AND status='Delivered'" : '';
    $query = $pdo->prepare("SELECT order_number,customer_name,customer_phone,customer_email,delivery_address,city,district,subtotal,discount_total,delivery_charge,total,payment_method,payment_status,status,created_at FROM orders WHERE created_at BETWEEN ? AND ?{$salesFilter} ORDER BY created_at DESC");
    $query->execute([$start,$end]);
    $filename = $export === 'sales' ? 'more-and-more-sales.csv' : 'more-and-more-orders.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Order number','Customer','Phone','Email','Delivery address','City','District','Subtotal LKR','Discount LKR','Delivery LKR','Total LKR','Payment method','Payment status','Order status','Created at']);
    foreach ($query as $row) {
        fputcsv($output, array_map(static function (mixed $value): mixed {
            if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) return "'" . $value;
            return $value;
        }, array_values($row)));
    }
    fclose($output);
    exit;
}
if ($export === 'inventory') {
    $query = $pdo->query('SELECT p.sku,p.name,c.name AS category,p.price,p.discounted_price,p.stock_quantity,p.is_published FROM products p JOIN categories c ON c.id=p.category_id ORDER BY c.name,p.name');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="more-and-more-inventory.csv"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['SKU','Product','Category','Price LKR','Discount price LKR','Stock','Published']);
    foreach ($query as $row) fputcsv($output, array_map(static fn (mixed $value): mixed => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value, array_values($row)));
    fclose($output);
    exit;
}
$summary = $pdo->prepare("SELECT COUNT(*) AS order_count,COALESCE(SUM(total),0) AS order_value,COALESCE(SUM(CASE WHEN payment_status='paid' AND status='Delivered' THEN total ELSE 0 END),0) AS paid_sales,COALESCE(SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END),0) AS cancellations FROM orders WHERE created_at BETWEEN ? AND ?");
$summary->execute([$start,$end]);
$totals = $summary->fetch();
$daily = $pdo->prepare("SELECT DATE(created_at) AS order_day,COUNT(*) AS order_count,COALESCE(SUM(total),0) AS order_value,COALESCE(SUM(CASE WHEN payment_status='paid' AND status='Delivered' THEN total ELSE 0 END),0) AS paid_sales FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY order_day DESC");
$daily->execute([$start,$end]);
$inventoryPage = min(100000, max(1, (int) ($_GET['inventory_page'] ?? 1)));
$inventoryTotal = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$inventoryPage = min(max(1, (int) ceil($inventoryTotal / 50)), $inventoryPage);
$inventory = $pdo->query('SELECT p.id,p.sku,p.name,c.name AS category,p.price,p.discounted_price,p.stock_quantity,p.is_published FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.stock_quantity,p.name LIMIT 50 OFFSET ' . (($inventoryPage - 1) * 50))->fetchAll();
require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-title"><div><h1>Reports</h1><p>Order and inventory summaries calculated from real store records. Paid sales include paid, delivered orders only.</p></div></div>
<form class="filter-form" method="get"><label>From<input type="date" name="from" value="<?= h($from) ?>"></label><label>To<input type="date" name="to" value="<?= h($to) ?>"></label><button class="button button-dark">Update report</button><a class="small-button" href="reports.php?export=orders&amp;from=<?= rawurlencode($from) ?>&amp;to=<?= rawurlencode($to) ?>">Export orders CSV</a><a class="small-button" href="reports.php?export=sales&amp;from=<?= rawurlencode($from) ?>&amp;to=<?= rawurlencode($to) ?>">Export sales CSV</a><a class="small-button" href="reports.php?export=inventory">Export inventory CSV</a></form>
<div class="stats-grid"><article class="stat-card"><span>Orders in period</span><strong><?= number_format((int) $totals['order_count']) ?></strong></article><article class="stat-card"><span>Order value</span><strong><?= money($totals['order_value']) ?></strong></article><article class="stat-card"><span>Paid completed sales</span><strong><?= money($totals['paid_sales']) ?></strong></article><article class="stat-card"><span>Cancellations</span><strong><?= number_format((int) $totals['cancellations']) ?></strong></article></div>
<section class="admin-panel"><h2>Daily orders</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Orders</th><th>Order value</th><th>Paid sales</th></tr></thead><tbody><?php foreach ($daily->fetchAll() as $row): ?><tr><td><?= h(date('j M Y', strtotime($row['order_day']))) ?></td><td><?= (int) $row['order_count'] ?></td><td><?= money($row['order_value']) ?></td><td><?= money($row['paid_sales']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="admin-panel"><div class="admin-title"><h2>Inventory report (<?= $inventoryTotal ?> products)</h2><a class="text-link" href="reports.php?export=inventory">Download CSV →</a></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Price</th><th>Discount</th><th>Stock</th><th>Visibility</th></tr></thead><tbody><?php foreach ($inventory as $item): ?><tr><td><a href="products.php?edit=<?= (int) $item['id'] ?>"><?= h($item['name']) ?></a></td><td><?= h($item['sku']) ?></td><td><?= h($item['category']) ?></td><td><?= money($item['price']) ?></td><td><?= $item['discounted_price'] !== null ? money($item['discounted_price']) : '—' ?></td><td><?= (int) $item['stock_quantity'] ?></td><td><?= $item['is_published'] ? 'Published' : 'Hidden' ?></td></tr><?php endforeach; ?></tbody></table><?php catalog_pagination($inventoryTotal,$inventoryPage,50,'inventory_page'); ?></div></section>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
