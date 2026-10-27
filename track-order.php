<?php
require __DIR__ . '/includes/bootstrap.php';
$order = null;
$history = [];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Cache-Control: no-store, private');
    verify_csrf();
    $number = strtoupper(trim((string) ($_POST['order_number'] ?? '')));
    $code = trim((string) ($_POST['tracking_code'] ?? ''));
    $ipHash = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), getenv('APP_KEY') ?: '');
    $pdo->exec('DELETE FROM order_tracking_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 30 DAY');
    $rateLimit = $pdo->prepare('SELECT COUNT(*) FROM order_tracking_attempts WHERE ip_hash=? AND attempted_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE');
    $rateLimit->execute([$ipHash]);
    $limited = (int) $rateLimit->fetchColumn() >= 20;
    if (!$limited) {
        $pdo->prepare('INSERT INTO order_tracking_attempts (ip_hash) VALUES (?)')->execute([$ipHash]);
    }
    if (!$limited && preg_match('/^MM[0-9]{6}[A-F0-9]{8}$/', $number) && preg_match('/^[A-Za-z0-9_-]{20,40}$/', $code)) {
        $query = $pdo->prepare('SELECT order_number,status,created_at,city,district,total FROM orders WHERE order_number=? AND tracking_hash=? LIMIT 1');
        $query->execute([$number, hash('sha256', $code)]);
        $order = $query->fetch() ?: null;
        if ($order) {
            $statusQuery = $pdo->prepare('SELECT new_status,change_note,changed_at FROM order_status_history WHERE order_id=(SELECT id FROM orders WHERE order_number=?) ORDER BY changed_at,id');
            $statusQuery->execute([$number]);
            $history = $statusQuery->fetchAll();
        } elseif ($limited) {
            $error = 'Too many tracking attempts. Please wait 15 minutes before trying again.';
        } else {
            $error = 'We could not verify those details. Check your order number and private tracking code.';
        }
    } else {
        $error = 'Enter a valid order number and tracking code.';
    }
}
$pageTitle = 'Track an order';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><span class="eyebrow">Order updates</span><h1>Track your order</h1><p>Enter the order number and private tracking code provided at checkout.</p></section>
<section class="section-wrap tracking-section"><form class="tracking-form" method="post"><?= csrf_field() ?><label>Order number<input name="order_number" maxlength="32" placeholder="MM260101A1B2C3D4" required></label><label>Private tracking code<input name="tracking_code" minlength="20" maxlength="40" autocomplete="off" required></label><button class="button button-dark">View order status</button></form>
<?php if ($error): ?><p class="notice notice-error"><?= h($error) ?></p><?php endif; ?>
<?php if ($order): ?><div class="tracking-result"><span class="eyebrow">Order <?= h($order['order_number']) ?></span><h2><?= h($order['status']) ?></h2><p>Placed <?= h(date('j M Y, g:i a', strtotime($order['created_at']))) ?> · <?= h($order['city']) ?>, <?= h($order['district']) ?></p><strong>Total <?= money($order['total']) ?></strong><ol class="status-timeline"><?php foreach ($history as $event): ?><li><strong><?= h($event['new_status']) ?></strong><time><?= h(date('j M Y, g:i a', strtotime($event['changed_at']))) ?></time><?php if ($event['change_note']): ?><span><?= h($event['change_note']) ?></span><?php endif; ?></li><?php endforeach; ?></ol></div><?php endif; ?></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
