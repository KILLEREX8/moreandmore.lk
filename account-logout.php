<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();
unset($_SESSION['customer_id'], $_SESSION['customer_username']);
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
flash('success', 'You have signed out of your account.');
redirect('index.php');
