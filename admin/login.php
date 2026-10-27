<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/customer-auth.php';
require_admin_login_route();
if (!empty($_SESSION['admin_id'])) {
    redirect(application_url('/admin/index.php'));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = strtolower(substr(trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : ''), 0, 80));
    $password = is_string($_POST['password'] ?? null) ? substr($_POST['password'], 0, 1024) : '';
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ipHash = hash_hmac('sha256', $ip, getenv('APP_KEY') ?: '');
    $pdo->exec('DELETE FROM admin_login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 30 DAY');
    $check = $pdo->prepare('SELECT COUNT(*) FROM admin_login_attempts WHERE ip_hash=? AND was_successful=0 AND attempted_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE');
    $check->execute([$ipHash]);
    $locked = (int) $check->fetchColumn() >= 5;
    $query = $pdo->prepare('SELECT id,name,username,password_hash FROM admin_users WHERE username=? AND is_active=1 LIMIT 1');
    $query->execute([$username]);
    $admin = $query->fetch();
    $valid = !$locked && valid_username(strtolower($username)) && $admin && password_verify($password, $admin['password_hash']);
    $attempt = $pdo->prepare('INSERT INTO admin_login_attempts (ip_hash,was_successful) VALUES (?,?)');
    $attempt->execute([$ipHash, $valid ? 1 : 0]);
    if ($valid) {
        rotate_admin_login_token();
        session_regenerate_id(true);
        unset($_SESSION['customer_id'], $_SESSION['customer_username']);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE admin_users SET last_login_at=UTC_TIMESTAMP() WHERE id=?')->execute([$admin['id']]);
        $pdo->prepare('DELETE FROM admin_login_attempts WHERE ip_hash=? AND attempted_at < UTC_TIMESTAMP() - INTERVAL 1 DAY')->execute([$ipHash]);
        redirect(application_url('/admin/index.php'));
    }
    $error = $locked ? 'Too many sign-in attempts. Try again in 15 minutes.' : 'The username or password was not recognised.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin sign in · More & More</title><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/admin.css"></head><body class="login-page"><main class="login-card"><a class="brand" href="../index.php">More & More</a><span class="eyebrow">Store administration</span><h1>Welcome back.</h1><p>Sign in with your authorized administrator username.</p><?php if ($error): ?><div class="notice notice-error"><?= h($error) ?></div><?php endif; ?><form method="post" data-disable-on-submit><?= csrf_field() ?><label>Username<input name="username" autocomplete="username" maxlength="80" required></label><label>Password<input name="password" type="password" autocomplete="current-password" required></label><button class="button button-dark">Sign in</button></form><p><a class="text-link" href="../index.php">Return to store</a></p></main><script src="../assets/js/main.js" defer></script></body></html>
