<?php
declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || getenv('SESSION_SECURE') === '1';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('moreandmore_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (empty($_SESSION['session_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['session_initialized'] = time();
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        exit('Your session expired. Refresh the page and try again.');
    }
}

function application_url(string $path): string
{
    $baseUrl = rtrim((string) (getenv('APP_URL') ?: ''), '/');
    return $baseUrl . '/' . ltrim($path, '/');
}

function current_admin_login_token(): ?string
{
    global $pdo;
    if (!isset($pdo)) {
        return null;
    }

    $query = $pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key=?');
    $query->execute(['admin_login_token']);
    $token = $query->fetchColumn();
    if ($token === false || $token === '') {
        $token = getenv('ADMIN_LOGIN_TOKEN') ?: '';
    }

    return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) ? $token : null;
}

function admin_login_url(?string $token = null): ?string
{
    $token ??= current_admin_login_token();
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return application_url('admin-entry-' . substr($token, 0, 32) . '-' . substr($token, 32));
}

function require_admin_login_route(): void
{
    $token = current_admin_login_token();
    $loginUrl = admin_login_url($token);
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $expectedPath = $loginUrl === null ? '' : parse_url($loginUrl, PHP_URL_PATH);
    if ($loginUrl === null || !is_string($requestPath) || !is_string($expectedPath)
        || !hash_equals(rtrim($expectedPath, '/'), rtrim($requestPath, '/'))) {
        http_response_code(404);
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        exit('Not found.');
    }
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: no-referrer');
}

function rotate_admin_login_token(): string
{
    global $pdo;
    $token = bin2hex(random_bytes(32));
    save_setting($pdo, 'admin_login_token', $token);
    return $token;
}

function require_admin(): void
{
    global $pdo;
    if (empty($_SESSION['admin_id']) || !isset($pdo)) {
        http_response_code(404);
        exit('Not found.');
    }
    $query = $pdo->prepare('SELECT name,username,must_change_password FROM admin_users WHERE id=? AND is_active=1');
    $query->execute([(int) $_SESSION['admin_id']]);
    $admin = $query->fetch();
    if (!$admin) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_username']);
        http_response_code(404);
        exit('Not found.');
    }
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_username'] = $admin['username'];
    if ((int) $admin['must_change_password'] === 1
        && !str_ends_with(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/admin/change-password.php')) {
        flash('error', 'Change your administrator password to continue.');
        redirect(application_url('/admin/change-password.php'));
    }
}
