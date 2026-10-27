<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer-auth.php';
if (current_customer() !== null) {
    redirect('account.php');
}
$pageTitle = 'Customer sign in';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = strtolower(trim(substr(is_string($_POST['username'] ?? null) ? $_POST['username'] : '', 0, 32)));
    $password = is_string($_POST['password'] ?? null) ? substr($_POST['password'], 0, 72) : '';
    $ipHash = customer_login_ip_hash();
    $locked = customer_login_is_locked($pdo, $ipHash);
    $query = $pdo->prepare('SELECT id,username,password_hash FROM customers WHERE username=? AND is_active=1 LIMIT 1');
    $query->execute([$username]);
    $customer = $query->fetch();
    $valid = !$locked && valid_username($username) && $customer && password_verify($password, $customer['password_hash']);
    record_customer_login_attempt($pdo, $ipHash, (bool) $valid);
    if ($valid) {
        session_regenerate_id(true);
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_username']);
        $_SESSION['customer_id'] = (int) $customer['id'];
        $_SESSION['customer_username'] = $customer['username'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE customers SET last_login_at=UTC_TIMESTAMP() WHERE id=?')->execute([$customer['id']]);
        redirect('account.php');
    }
    $error = $locked ? 'Too many sign-in attempts. Try again in 15 minutes.' : 'The username or password was not recognised.';
}
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero compact-hero"><span class="eyebrow">Customer account</span><h1>Welcome back.</h1><p>Sign in with your username. No email needed.</p></section>
<section class="section-wrap account-section"><div class="form-panel account-panel">
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="account-form" data-disable-on-submit><?= csrf_field() ?>
<label>Username<input name="username" autocomplete="username" maxlength="32" required></label>
<label>Password<input name="password" type="password" autocomplete="current-password" maxlength="72" required></label>
<button class="button button-dark">Sign in</button></form>
<p>New here? <a class="text-link" href="account-register.php">Create an account</a></p>
<p>Want to shop without an account? <a class="text-link" href="products.php">Continue as a guest</a></p>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
