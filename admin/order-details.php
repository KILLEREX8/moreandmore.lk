<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    flash('error', 'Order not found.');
    redirect('orders.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $newStatus = (string) ($_POST['status'] ?? '');
    $paymentStatus = (string) ($_POST['payment_status'] ?? '');
    $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));
    $cancelReason = trim((string) ($_POST['cancellation_reason'] ?? ''));
    if (!in_array($newStatus, order_statuses(), true) || !in_array($paymentStatus, ['unpaid','paid','refunded'], true) || strlen($adminNotes) > 5000 || strlen($cancelReason) > 500) {
        flash('error', 'Please check the status, payment status and note lengths.');
        redirect('order-details.php?id=' . $id);
    }
    try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT status,payment_status FROM orders WHERE id=? FOR UPDATE');
        $lock->execute([$id]);
        $current = $lock->fetch();
        if (!$current) throw new DomainException('Order not found.');
        if ($current['status'] === 'Cancelled' && $newStatus !== 'Cancelled') {
            throw new DomainException('A cancelled order cannot be reopened.');
        }
        if ($newStatus === 'Cancelled' && trim($cancelReason) === '') {
            throw new DomainException('Add a cancellation reason before cancelling the order.');
        }
        if ($newStatus === 'Cancelled' && $current['status'] !== 'Cancelled') {
            $items = $pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=? AND product_id IS NOT NULL');
            $items->execute([$id]);
            $restore = $pdo->prepare('UPDATE products SET stock_quantity=stock_quantity+? WHERE id=?');
            foreach ($items->fetchAll() as $item) $restore->execute([(int) $item['quantity'],(int) $item['product_id']]);
        }
        $update = $pdo->prepare('UPDATE orders SET status=?,payment_status=?,admin_notes=?,cancellation_reason=? WHERE id=?');
        $update->execute([$newStatus,$paymentStatus,$adminNotes ?: null,$newStatus === 'Cancelled' ? $cancelReason : null,$id]);
        if ($current['status'] !== $newStatus) {
            $history = $pdo->prepare('INSERT INTO order_status_history (order_id,previous_status,new_status,change_note,changed_by_admin_id) VALUES (?,?,?,?,?)');
            $history->execute([$id,$current['status'],$newStatus,$newStatus === 'Cancelled' ? 'Cancellation reason: ' . $cancelReason : null,$_SESSION['admin_id']]);
        }
        $pdo->commit();
        flash('success', 'Order changes saved.');
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Order update failed: ' . $exception->getMessage());
        flash('error', 'The order could not be updated.');
    }
    redirect('order-details.php?id=' . $id);
}
$query = $pdo->prepare('SELECT * FROM orders WHERE id=?');
$query->execute([$id]);
$order = $query->fetch();
if (!$order) {
    flash('error', 'Order not found.');
    redirect('orders.php');
}
$itemsQuery = $pdo->prepare('SELECT product_name,sku,unit_price,quantity,line_total FROM order_items WHERE order_id=? ORDER BY id');
$itemsQuery->execute([$id]);
$items = $itemsQuery->fetchAll();
$historyQuery = $pdo->prepare('SELECT h.*,a.name AS admin_name FROM order_status_history h LEFT JOIN admin_users a ON a.id=h.changed_by_admin_id WHERE h.order_id=? ORDER BY h.changed_at,h.id');
$historyQuery->execute([$id]);
$history = $historyQuery->fetchAll();
$adminPageTitle = 'Order ' . $order['order_number'];
require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-title no-print"><div><h1>Order <?= h($order['order_number']) ?></h1><p>Placed <?= h(date('j M Y, g:i a', strtotime($order['created_at']))) ?></p></div><button class="button button-dark" type="button" data-print-invoice>Print invoice</button></div>
<section class="admin-panel"><div class="invoice-head"><div><h2 class="print-only">More & More · Invoice</h2><strong><?= h($order['customer_name']) ?></strong><p><?= h($order['customer_phone']) ?><br><?= h($order['customer_email'] ?? '') ?></p><p class="order-address"><?= h($order['delivery_address']) ?><br><?= h($order['city']) ?>, <?= h($order['district']) ?> <?= h($order['postal_code']) ?></p></div><div><strong><?= h($order['order_number']) ?></strong><p><?= h($order['status']) ?> · <?= h(strtoupper($order['payment_method'])) ?></p><p>Payment: <?= h($order['payment_status']) ?></p></div></div>
<div class="admin-table-wrap"><table class="admin-table invoice-table"><thead><tr><th>Product</th><th>SKU</th><th>Unit price</th><th>Qty</th><th>Total</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><?= h($item['product_name']) ?></td><td><?= h($item['sku']) ?></td><td><?= money($item['unit_price']) ?></td><td><?= (int) $item['quantity'] ?></td><td><?= money($item['line_total']) ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="4">Subtotal</th><td><?= money($order['subtotal']) ?></td></tr><tr><th colspan="4">Discount</th><td><?= money($order['discount_total']) ?></td></tr><tr><th colspan="4">Delivery</th><td><?= money($order['delivery_charge']) ?></td></tr><tr><th colspan="4">Total</th><td><strong><?= money($order['total']) ?></strong></td></tr></tfoot></table></div><?php if ($order['order_notes']): ?><p><strong>Customer notes:</strong> <?= nl2br(h($order['order_notes'])) ?></p><?php endif; ?></section>
<div class="admin-grid"><section class="admin-panel"><h2>Update order</h2><form method="post" class="admin-form"><?= csrf_field() ?><label>Status<select name="status"><?php foreach (order_statuses() as $status): ?><option value="<?= h($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= h($status === 'Processing' ? 'Processing — packing order' : ($status === 'Shipped' ? 'Shipped — in transit' : $status)) ?></option><?php endforeach; ?></select></label><label>Payment status<select name="payment_status"><?php foreach (['unpaid','paid','refunded'] as $status): ?><option value="<?= h($status) ?>" <?= $order['payment_status'] === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select></label><label class="wide">Internal admin notes<textarea name="admin_notes" rows="4" maxlength="5000"><?= h($order['admin_notes'] ?? '') ?></textarea><small class="spec-help">Private to admins and not visible to the customer.</small></label><label class="wide">Cancellation reason <small>(required if cancelling)</small><textarea name="cancellation_reason" rows="2" maxlength="500"><?= h($order['cancellation_reason'] ?? '') ?></textarea></label><button class="button button-dark">Save order</button></form></section>
<section class="admin-panel"><h2>Status history</h2><ol class="status-timeline"><?php foreach ($history as $event): ?><li><strong><?= h($event['new_status']) ?></strong><time><?= h(date('j M Y, g:i a', strtotime($event['changed_at']))) ?></time><?php if ($event['change_note']): ?><span><?= h($event['change_note']) ?></span><?php endif; ?><?php if ($event['admin_name']): ?><small>By <?= h($event['admin_name']) ?></small><?php endif; ?></li><?php endforeach; ?></ol></section></div>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
