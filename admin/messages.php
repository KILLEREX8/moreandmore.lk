<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/catalog.php';
require_admin();
$adminPageTitle = 'Contact messages';
$term = trim(substr(is_string($_GET['q'] ?? null) ? $_GET['q'] : '', 0, 100));
$page = min(100000, max(1, (int) ($_GET['page'] ?? 1)));
$where = '';
$params = [];
if ($term !== '') {
    $where = 'WHERE name LIKE ? OR email LIKE ? OR subject LIKE ?';
    foreach ([$term,$term,$term] as $value) $params[] = '%' . $value . '%';
}
$countQuery = $pdo->prepare("SELECT COUNT(*) FROM contact_messages {$where}");
$countQuery->execute($params);
$messageCount = (int) $countQuery->fetchColumn();
$page = min(max(1, (int) ceil($messageCount / 25)), $page);
$query = $pdo->prepare("SELECT id,name,email,phone,subject,message,created_at FROM contact_messages {$where} ORDER BY created_at DESC LIMIT 25 OFFSET " . (($page - 1) * 25));
$query->execute($params);
$messages = $query->fetchAll();
require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-title"><div><h1>Contact messages</h1><p>Messages submitted through the public contact form.</p></div></div>
<form class="filter-form" method="get"><label>Search<input name="q" value="<?= h($term) ?>" placeholder="Name, email or subject"></label><button class="button button-dark">Search</button></form>
<section class="admin-panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Received</th><th>Customer</th><th>Subject and message</th></tr></thead><tbody><?php foreach ($messages as $message): ?><tr><td><?= h(date('j M Y, g:i a', strtotime($message['created_at']))) ?></td><td><strong><?= h($message['name']) ?></strong><br><a href="mailto:<?= h($message['email']) ?>"><?= h($message['email']) ?></a><br><?= h($message['phone'] ?? '') ?></td><td><strong><?= h($message['subject']) ?></strong><p><?= nl2br(h($message['message'])) ?></p></td></tr><?php endforeach; ?><?php if (!$messages): ?><tr><td colspan="3">No messages match the search.</td></tr><?php endif; ?></tbody></table><?php catalog_pagination($messageCount,$page,25); ?></section>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
