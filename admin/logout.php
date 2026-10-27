<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $loginUrl = admin_login_url();
    redirect($loginUrl ?? application_url('/'));
}
verify_csrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
$loginUrl = admin_login_url();
redirect($loginUrl ?? application_url('/'));
