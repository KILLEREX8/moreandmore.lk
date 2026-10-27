<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$adminPageTitle = 'Change password';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    $query = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id=? AND is_active=1');
    $query->execute([(int) $_SESSION['admin_id']]);
    $hash = $query->fetchColumn();
    if (!$hash || !password_verify($currentPassword, (string) $hash)) {
        $error = 'The current password was not recognised.';
    } elseif (strlen($newPassword) < 12 || strlen($newPassword) > 72 || !hash_equals($newPassword, $confirmation)) {
        $error = 'Choose a new password of 12–72 characters and enter it the same way twice.';
    } elseif (password_verify($newPassword, (string) $hash)) {
        $error = 'Choose a password different from your current password.';
    } else {
        $update = $pdo->prepare('UPDATE admin_users SET password_hash=?,must_change_password=0 WHERE id=?');
        $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $_SESSION['admin_id']]);
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        flash('success', 'Administrator password updated.');
        redirect('index.php');
    }
}
require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-title"><div><h1>Change password</h1><p>Use a unique password that is 12–72 characters long.</p></div></div>
<section class="admin-panel account-panel"><?php if ($error): ?><div class="notice notice-error"><?= h($error) ?></div><?php endif; ?>
<form class="account-form" method="post" data-disable-on-submit><?= csrf_field() ?>
<label>Current password<input type="password" name="current_password" autocomplete="current-password" maxlength="1024" required></label>
<label>New password<input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<button class="button button-dark">Update password</button></form></section>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
