<?php
require __DIR__ . '/includes/bootstrap.php';
header('Cache-Control: no-store, private');
$confirmation = $_SESSION['order_confirmation'] ?? null;
if (!$confirmation || !isset($confirmation['number'], $confirmation['tracking_code'])) {
    flash('error', 'Order confirmation is available immediately after placing an order.');
    redirect('index.php');
}
$pageTitle = 'Order confirmed';
require __DIR__ . '/includes/header.php';
?>
<section class="section-wrap success-section"><span class="success-mark">✓</span><span class="eyebrow">Thank you for shopping with us</span><h1>Your order is <em>confirmed.</em></h1><p>We received your order. Our team will contact you to coordinate delivery and payment on arrival.</p><div class="confirmation-card"><span>Order number</span><strong><?= h($confirmation['number']) ?></strong><span>Private tracking code — save this code</span><strong class="tracking-code"><?= h($confirmation['tracking_code']) ?></strong><small>For your privacy, this tracking code is shown only once. Keep it with your order number.</small></div><a class="button button-dark" href="track-order.php">Track your order</a><a class="text-link" href="products.php">Continue shopping</a></section>
<?php unset($_SESSION['order_confirmation']); require __DIR__ . '/includes/footer.php'; ?>
