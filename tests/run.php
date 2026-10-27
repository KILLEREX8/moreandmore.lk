<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/catalog.php';
require dirname(__DIR__) . '/includes/security.php';
require dirname(__DIR__) . '/includes/customer-auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Failed: ' . $message);
    }
    $checks++;
};

$check(h('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;', 'HTML output is escaped');
$check(money(1250) === 'LKR 1,250.00', 'LKR formatting');
$check(product_price(['price' => 1000, 'discounted_price' => 750]) === 750, 'discounted price is selected');
$check(product_price(['price' => 1000, 'discounted_price' => null]) === 1000, 'regular price is selected');
$check(valid_email(null), 'email can be omitted for guest checkout');
$check(valid_email('buyer@example.com'), 'valid email is accepted');
$check(!valid_email('not-an-email'), 'invalid email is rejected');
$check(whatsapp_contact_url('+94 77 123 4567') === 'https://wa.me/94771234567', 'WhatsApp URL uses the configured phone number with country code');
$check(whatsapp_contact_url('') === null, 'WhatsApp contact is disabled when no number is configured');
$check(whatsapp_contact_url('123') === null, 'invalid WhatsApp phone numbers are rejected');
$check(valid_username('killerex'), 'simple customer username is accepted');
$check(valid_username('buyer_01'), 'username with supported punctuation is accepted');
$check(!valid_username('ab'), 'username shorter than three characters is rejected');
$check(!valid_username('buyer@example.com'), 'email addresses cannot be used as usernames');
$check(!valid_username('not valid'), 'whitespace is rejected in usernames');
$check(order_statuses() === ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'], 'supported order statuses');
$check(customer_order_status_description('Pending') === 'We have received your order.', 'customers receive a pending order update');
$check(customer_order_status_description('Processing') === 'We are preparing your package.', 'processing status is described as package preparation');
$check(customer_order_status_description('Shipped') === 'Your package is on the way.', 'shipped status is described as delivery progress');
$check(valid_date('2026-10-28'), 'valid date is accepted');
$check(!valid_date('2026-20-45'), 'invalid calendar date is rejected');
$check(!valid_date('not-a-date'), 'non-date input is rejected');
$passwordHash = password_hash('strong-test-password', PASSWORD_DEFAULT);
$check(password_verify('strong-test-password', $passwordHash), 'password hash verifies');
$check(!password_verify('incorrect-password', $passwordHash), 'incorrect password fails verification');
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_POST['csrf_token'] = $_SESSION['csrf_token'];
verify_csrf();
$check(true, 'valid CSRF token passes verification');
$previousAppUrl = getenv('APP_URL');
$previousAdminToken = getenv('ADMIN_LOGIN_TOKEN');
putenv('APP_URL=https://shop.example/store');
putenv('ADMIN_LOGIN_TOKEN=' . str_repeat('a', 64));
$check(admin_login_url(str_repeat('a', 64)) === 'https://shop.example/store/admin-entry-' . str_repeat('a', 32) . '-' . str_repeat('a', 32), 'admin entry URL uses two secret components and the application base path');
$check(admin_login_url('not-a-valid-token') === null, 'invalid secret admin tokens disable the login route');
if ($previousAppUrl === false) {
    putenv('APP_URL');
} else {
    putenv('APP_URL=' . $previousAppUrl);
}
if ($previousAdminToken === false) {
    putenv('ADMIN_LOGIN_TOKEN');
} else {
    putenv('ADMIN_LOGIN_TOKEN=' . $previousAdminToken);
}

echo "Passed {$checks} PHP checks.\n";
