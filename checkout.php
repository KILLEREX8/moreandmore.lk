<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer-auth.php';
header('Cache-Control: no-store, private');
$customer = current_customer();
$items = cart_items($pdo);
if (!$items) {
    flash('error', 'Your shopping bag is empty.');
    redirect('cart.php');
}
$pageTitle = 'Guest checkout';
$districts = ['Ampara','Anuradhapura','Badulla','Batticaloa','Colombo','Galle','Gampaha','Hambantota','Jaffna','Kalutara','Kandy','Kegalle','Kilinochchi','Kurunegala','Mannar','Matale','Matara','Monaragala','Mullaitivu','Nuwara Eliya','Polonnaruwa','Puttalam','Ratnapura','Trincomalee','Vavuniya'];
$subtotal = array_sum(array_map(static fn (array $item): int => (int) $item['price'] * (int) $item['quantity'], $items));
$discountTotal = array_sum(array_map(static fn (array $item): int => max(0, (int) $item['price'] - product_price($item)) * (int) $item['quantity'], $items));
$itemsTotal = $subtotal - $discountTotal;
$deliveryEnabled = setting($pdo, 'delivery_enabled', '1') === '1';
$freeThreshold = max(0, (int) setting($pdo, 'delivery_free_threshold', '0'));
$delivery = !$deliveryEnabled || ($freeThreshold > 0 && $itemsTotal >= $freeThreshold) ? 0 : max(0, (int) setting($pdo, 'delivery_default_charge', '500'));
$orderToken = $_SESSION['checkout_token'] ??= bin2hex(random_bytes(24));
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero compact-hero"><span class="eyebrow">Secure checkout · No account required</span><h1>Delivery details</h1></section>
<section class="section-wrap checkout-section"><form class="checkout-form" action="place-order.php" method="post" data-disable-on-submit>
<?= csrf_field() ?><input type="hidden" name="checkout_token" value="<?= h($orderToken) ?>">
<div class="form-panel"><h2>Your details</h2><div class="form-grid">
<label class="wide">Full name<input name="customer_name" maxlength="160" autocomplete="name" value="<?= h($customer['full_name'] ?? '') ?>" required></label>
<label>Mobile number<input name="customer_phone" type="tel" maxlength="32" autocomplete="tel" pattern="[+0-9\(\) \-]{7,32}" value="<?= h($customer['phone'] ?? '') ?>" required></label>
<label>Email address <small>(optional)</small><input name="customer_email" type="email" maxlength="254" autocomplete="email"></label>
<label class="wide">Delivery address<input name="delivery_address" maxlength="500" autocomplete="street-address" required></label>
<label>City or town<input name="city" maxlength="100" autocomplete="address-level2" required></label>
<label>District<select name="district" required><option value="">Select district</option><?php foreach ($districts as $district): ?><option><?= h($district) ?></option><?php endforeach; ?></select></label>
<label>Postal code<input name="postal_code" maxlength="20" autocomplete="postal-code"></label>
<label class="wide">Order notes <small>(optional)</small><textarea name="order_notes" maxlength="2000" rows="3"></textarea></label>
</div></div>
<aside class="cart-summary checkout-summary"><h2>Your order</h2><?php foreach ($items as $item): ?><div><span><?= h($item['name']) ?> × <?= (int) $item['quantity'] ?></span><strong><?= money(product_price($item) * (int) $item['quantity']) ?></strong></div><?php endforeach; ?><div><span>Subtotal</span><strong><?= money($subtotal) ?></strong></div><?php if ($discountTotal): ?><div><span>Discount</span><strong>−<?= money($discountTotal) ?></strong></div><?php endif; ?><div><span>Delivery</span><strong><?= $delivery ? money($delivery) : 'Free' ?></strong></div><div class="summary-total"><span>Total</span><strong><?= money($itemsTotal + $delivery) ?></strong></div>
<?php if (setting($pdo, 'delivery_service_areas', 'Islandwide') !== ''): ?><p>Delivery areas: <?= h(setting($pdo, 'delivery_service_areas', 'Islandwide')) ?></p><?php endif; ?>
<?php if (setting($pdo, 'cod_enabled', '1') === '1'): ?><fieldset><legend>Payment method</legend><label class="radio-option"><input type="radio" name="payment_method" value="cod" checked> Cash on delivery</label></fieldset><?php else: ?><p>Checkout is temporarily unavailable while payment options are configured.</p><?php endif; ?>
<button class="button button-dark button-full" <?= setting($pdo, 'cod_enabled', '1') !== '1' ? 'disabled' : '' ?>>Place order</button><small>No account or registration is needed.</small></aside>
</form></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
