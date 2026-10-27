<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('checkout.php');
}
verify_csrf();
$sessionToken = $_SESSION['checkout_token'] ?? '';
$submittedToken = (string) ($_POST['checkout_token'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    flash('error', 'This checkout was already submitted or has expired. Review your bag and try again.');
    redirect('cart.php');
}
$name = trim((string) ($_POST['customer_name'] ?? ''));
$phone = trim((string) ($_POST['customer_phone'] ?? ''));
$email = trim((string) ($_POST['customer_email'] ?? ''));
$address = trim((string) ($_POST['delivery_address'] ?? ''));
$city = trim((string) ($_POST['city'] ?? ''));
$district = trim((string) ($_POST['district'] ?? ''));
$postal = trim((string) ($_POST['postal_code'] ?? ''));
$notes = trim((string) ($_POST['order_notes'] ?? ''));
$paymentMethod = (string) ($_POST['payment_method'] ?? '');
$districts = ['Ampara','Anuradhapura','Badulla','Batticaloa','Colombo','Galle','Gampaha','Hambantota','Jaffna','Kalutara','Kandy','Kegalle','Kilinochchi','Kurunegala','Mannar','Matale','Matara','Monaragala','Mullaitivu','Nuwara Eliya','Polonnaruwa','Puttalam','Ratnapura','Trincomalee','Vavuniya'];
$phoneDigits = preg_match_all('/\d/', $phone);
if ($name === '' || strlen($name) > 160 || !preg_match('/^[+0-9 ()-]{7,32}$/', $phone) || $phoneDigits < 7 || $phoneDigits > 15 || !valid_email($email === '' ? null : $email) || $address === '' || strlen($address) > 500 || strlen($city) > 100 || $city === '' || !in_array($district, $districts, true) || strlen($postal) > 20 || strlen($notes) > 2000) {
    flash('error', 'Please review your details. The name, valid mobile number, address, city and district are required.');
    redirect('checkout.php');
}
if ($paymentMethod !== 'cod' || setting($pdo, 'cod_enabled', '1') !== '1') {
    flash('error', 'The selected payment method is not available.');
    redirect('checkout.php');
}
$cart = $_SESSION['cart'] ?? [];
if (!is_array($cart) || $cart === []) {
    flash('error', 'Your shopping bag is empty.');
    redirect('cart.php');
}
try {
    $pdo->beginTransaction();
    $ids = array_values(array_unique(array_filter(array_map('intval', array_keys($cart)), static fn (int $id): bool => $id > 0)));
    sort($ids);
    $products = [];
    $select = $pdo->prepare('SELECT p.*, (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id AND pi.is_primary=1 LIMIT 1) AS image_path FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.is_published=1 AND c.is_active=1 FOR UPDATE');
    foreach ($ids as $id) {
        $select->execute([$id]);
        $product = $select->fetch();
        $quantity = filter_var($cart[$id] ?? null, FILTER_VALIDATE_INT);
        if (!$product || $quantity === false || $quantity < 1 || $quantity > 99 || $quantity > (int) $product['stock_quantity']) {
            throw new DomainException('A product or quantity in your bag is no longer available. Review your bag.');
        }
        $product['quantity'] = $quantity;
        $product['unit_price'] = product_price($product);
        $products[] = $product;
    }
    $subtotal = array_sum(array_map(static fn (array $product): int => (int) $product['price'] * $product['quantity'], $products));
    $discountTotal = array_sum(array_map(static fn (array $product): int => max(0, (int) $product['price'] - $product['unit_price']) * $product['quantity'], $products));
    $itemsTotal = $subtotal - $discountTotal;
    $deliveryEnabled = setting($pdo, 'delivery_enabled', '1') === '1';
    $serviceAreas = array_map('strtolower', array_filter(array_map('trim', preg_split('/[,;\r\n]+/', setting($pdo, 'delivery_service_areas', 'Islandwide')) ?: [])));
    if ($serviceAreas && !in_array('islandwide', $serviceAreas, true) && !in_array(strtolower($district), $serviceAreas, true)) {
        throw new DomainException('Delivery is not currently available in that district. Contact us for assistance.');
    }
    $freeThreshold = max(0, (int) setting($pdo, 'delivery_free_threshold', '0'));
    $deliveryCharge = !$deliveryEnabled || ($freeThreshold > 0 && $itemsTotal >= $freeThreshold)
        ? 0
        : max(0, (int) setting($pdo, 'delivery_default_charge', '500'));
    $orderNumber = 'MM' . date('ymd') . strtoupper(bin2hex(random_bytes(4)));
    $trackingCode = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    $customerId = (int) ($_SESSION['customer_id'] ?? 0);
    if ($customerId > 0) {
        $customerCheck = $pdo->prepare('SELECT id FROM customers WHERE id=? AND is_active=1');
        $customerCheck->execute([$customerId]);
        if ($customerCheck->fetchColumn() === false) {
            $customerId = 0;
            unset($_SESSION['customer_id'], $_SESSION['customer_username']);
        }
    }
    $insert = $pdo->prepare('INSERT INTO orders (customer_id,order_number,tracking_hash,customer_name,customer_phone,customer_email,delivery_address,city,district,postal_code,order_notes,subtotal,discount_total,delivery_charge,total,payment_method,payment_status,status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $insert->execute([$customerId > 0 ? $customerId : null, $orderNumber, hash('sha256', $trackingCode), $name, $phone, $email !== '' ? $email : null, $address, $city, $district, $postal, $notes !== '' ? $notes : null, $subtotal, $discountTotal, $deliveryCharge, $itemsTotal + $deliveryCharge, 'cod', 'unpaid', 'Pending']);
    $orderId = (int) $pdo->lastInsertId();
    $insertItem = $pdo->prepare('INSERT INTO order_items (order_id,product_id,product_name,sku,unit_price,quantity,line_total) VALUES (?,?,?,?,?,?,?)');
    $decrement = $pdo->prepare('UPDATE products SET stock_quantity=stock_quantity-? WHERE id=? AND stock_quantity>=?');
    foreach ($products as $product) {
        $insertItem->execute([$orderId, $product['id'], $product['name'], $product['sku'], $product['unit_price'], $product['quantity'], $product['unit_price'] * $product['quantity']]);
        $decrement->execute([$product['quantity'], $product['id'], $product['quantity']]);
        if ($decrement->rowCount() !== 1) {
            throw new DomainException('A product has just sold out. Please review your bag.');
        }
    }
    $history = $pdo->prepare('INSERT INTO order_status_history (order_id,previous_status,new_status,change_note) VALUES (?,NULL,?,?)');
    $history->execute([$orderId, 'Pending', $customerId > 0 ? 'Order placed from a customer account.' : 'Order received from guest checkout.']);
    $pdo->commit();
    $_SESSION['cart'] = [];
    unset($_SESSION['checkout_token']);
    $_SESSION['order_confirmation'] = ['number' => $orderNumber, 'tracking_code' => $trackingCode];
    redirect('order-success.php');
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $exception->getMessage());
    redirect('checkout.php');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Checkout failed: ' . $exception->getMessage());
    flash('error', 'We could not place your order just now. Please try again or contact the store.');
    redirect('checkout.php');
}
