<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer-auth.php';
if (current_customer() !== null) {
    redirect('account.php');
}
$pageTitle = 'Create an account';
$error = '';
$oldUsername = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
$oldName = is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '';
$oldPhone = is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = strtolower(trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : ''));
    $name = trim(is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '');
    $phone = trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    $phoneDigits = preg_match_all('/\d/', $phone);
    $ipHash = customer_login_ip_hash();
    if (customer_login_is_locked($pdo, $ipHash)) {
        $error = 'Too many attempts. Please try again in 15 minutes.';
    } elseif (!valid_username($username)
        || $name === ''
        || strlen($name) > 160
        || !preg_match('/^[+0-9 ()-]{7,32}$/', $phone)
        || $phoneDigits < 7
        || $phoneDigits > 15
        || strlen($password) < 12
        || strlen($password) > 72
        || !hash_equals($password, $confirmation)) {
        record_customer_login_attempt($pdo, $ipHash, false);
        $error = 'Enter a valid username, name and phone number, and matching passwords of 12–72 characters.';
    } else {
        try {
            $query = $pdo->prepare('INSERT INTO customers (username,full_name,phone,password_hash) VALUES (?,?,?,?)');
            $query->execute([$username, $name, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            record_customer_login_attempt($pdo, $ipHash, true);
            session_regenerate_id(true);
            unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_username']);
            $_SESSION['customer_id'] = (int) $pdo->lastInsertId();
            $_SESSION['customer_username'] = $username;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            flash('success', 'Your account is ready. Guest checkout is always available too.');
            redirect('account.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                record_customer_login_attempt($pdo, $ipHash, false);
                $error = 'That username is already in use. Please choose another.';
            } else {
                throw $exception;
            }
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero compact-hero"><span class="eyebrow">Optional customer account</span><h1>Create your account.</h1><p>No email address required.</p></section>
<section class="section-wrap account-section"><div class="form-panel account-panel">
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="account-form" data-disable-on-submit><?= csrf_field() ?>
<label>Username <small>(3–32 letters, numbers, dots, dashes or underscores)</small><input name="username" autocomplete="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{2,31}" value="<?= h($oldUsername) ?>" required></label>
<label>Full name<input name="full_name" autocomplete="name" maxlength="160" value="<?= h($oldName) ?>" required></label>
<label>Mobile number<input name="phone" type="tel" autocomplete="tel" maxlength="32" pattern="[+0-9\(\) \-]{7,32}" value="<?= h($oldPhone) ?>" required></label>
<label>Password <small>(12–72 characters)</small><input name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<label>Confirm password<input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<button class="button button-dark">Create account</button></form>
<p>Already have an account? <a class="text-link" href="account-login.php">Sign in</a></p>
<p>Prefer not to create an account? <a class="text-link" href="products.php">Shop as a guest</a></p>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
