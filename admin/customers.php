<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/catalog.php';
require_admin();
$adminPageTitle = 'Customers';
$customerActionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accountId = filter_var($_POST['account_id'] ?? null, FILTER_VALIDATE_INT);
    if ($accountId === false || $accountId < 1) {
        http_response_code(400);
        exit('Invalid customer account.');
    }
    $toggle = $pdo->prepare('UPDATE customers SET is_active=IF(is_active=1,0,1) WHERE id=?');
    $toggle->execute([$accountId]);
    if ($toggle->rowCount() !== 1) {
        $customerActionError = 'That account no longer exists or its status did not change.';
    } else {
        flash('success', 'Customer account status updated. Existing orders have been retained.');
        redirect('customers.php');
    }
}
$term = trim(substr((string) ($_GET['q'] ?? ''), 0, 100));
$accountSearch = '%' . $term . '%';
$accountWhere = $term === '' ? '' : 'WHERE c.username LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ?';
$accountsQuery = $pdo->prepare("SELECT c.id,c.username,c.full_name,c.phone,c.is_active,c.created_at,COUNT(o.id) AS order_count,MAX(o.created_at) AS latest_order FROM customers c LEFT JOIN orders o ON o.customer_id=c.id {$accountWhere} GROUP BY c.id ORDER BY c.created_at DESC LIMIT 100");
$accountsQuery->execute($term === '' ? [] : [$accountSearch, $accountSearch, $accountSearch]);
$accounts = $accountsQuery->fetchAll();
$page = min(100000, max(1, (int) ($_GET['page'] ?? 1)));
$params = [];
$where = 'WHERE customer_id IS NULL';
if ($term !== '') {
    $where .= ' AND (customer_name LIKE ? OR customer_phone LIKE ? OR customer_email LIKE ?)';
    foreach ([$term,$term,$term] as $value) $params[] = '%' . $value . '%';
}
$countQuery = $pdo->prepare("SELECT COUNT(*) FROM (SELECT customer_phone,customer_name,customer_email FROM orders {$where} GROUP BY customer_phone,customer_name,customer_email) AS customer_rows");
$countQuery->execute($params);
$customerCount = (int) $countQuery->fetchColumn();
$page = min(max(1, (int) ceil($customerCount / 50)), $page);
$query = $pdo->prepare("SELECT customer_name,customer_phone,customer_email,COUNT(*) AS order_count,MAX(created_at) AS latest_order,SUM(total) AS total_order_value FROM orders {$where} GROUP BY customer_phone,customer_name,customer_email ORDER BY latest_order DESC LIMIT 50 OFFSET " . (($page - 1) * 50));
$query->execute($params);
$customers = $query->fetchAll();
require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-title"><div><h1>Customers</h1><p>Registered accounts and guest-checkout contacts from real orders.</p></div></div>
<?php if ($customerActionError): ?><div class="notice notice-error"><?= h($customerActionError) ?></div><?php endif; ?>
<form class="filter-form" method="get"><label>Search<input name="q" value="<?= h($term) ?>" placeholder="Name, phone or email"></label><button class="button button-dark">Search</button></form>
<section class="admin-panel admin-table-wrap"><h2>Customer accounts</h2><table class="admin-table"><thead><tr><th>Username</th><th>Name</th><th>Mobile</th><th>Orders</th><th>Joined</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($accounts as $account): ?><tr><td><?= h($account['username']) ?></td><td><?= h($account['full_name']) ?></td><td><a href="tel:<?= h($account['phone']) ?>"><?= h($account['phone']) ?></a></td><td><?= (int) $account['order_count'] ?></td><td><?= h(date('j M Y', strtotime($account['created_at']))) ?></td><td><?= (int) $account['is_active'] === 1 ? 'Active' : 'Disabled' ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="account_id" value="<?= (int) $account['id'] ?>"><button class="button"><?= (int) $account['is_active'] === 1 ? 'Disable' : 'Enable' ?></button></form></td></tr><?php endforeach; ?><?php if (!$accounts): ?><tr><td colspan="7">No customer accounts found.</td></tr><?php endif; ?></tbody></table><p><small>Account access can be disabled or restored. Orders are preserved.</small></p></section>
<section class="admin-panel admin-table-wrap"><h2>Guest checkout customers</h2><table class="admin-table"><thead><tr><th>Customer</th><th>Mobile</th><th>Email</th><th>Orders</th><th>Latest order</th><th>Order value</th></tr></thead><tbody><?php foreach ($customers as $customer): ?><tr><td><?= h($customer['customer_name']) ?></td><td><a href="tel:<?= h($customer['customer_phone']) ?>"><?= h($customer['customer_phone']) ?></a></td><td><?= h($customer['customer_email'] ?? '—') ?></td><td><?= (int) $customer['order_count'] ?></td><td><?= h(date('j M Y', strtotime($customer['latest_order']))) ?></td><td><?= money($customer['total_order_value']) ?></td></tr><?php endforeach; ?><?php if (!$customers): ?><tr><td colspan="6">No guest checkout orders found.</td></tr><?php endif; ?></tbody></table><?php catalog_pagination($customerCount,$page,50); ?></section>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
