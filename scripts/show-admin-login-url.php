<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/database.php';

$query = $pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key=?');
$query->execute(['admin_login_token']);
$token = $query->fetchColumn();
if ($token === false || $token === '') {
    $token = getenv('ADMIN_LOGIN_TOKEN') ?: '';
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $insert = $pdo->prepare(
            "INSERT INTO store_settings (setting_key,setting_value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE setting_value=IF(setting_value='',VALUES(setting_value),setting_value)"
        );
        $insert->execute(['admin_login_token', $token]);
        $query->execute(['admin_login_token']);
        $token = $query->fetchColumn();
    }
}
$appUrl = rtrim((string) (getenv('APP_URL') ?: ''), '/');
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token) || $appUrl === '') {
    fwrite(STDERR, "Set APP_URL and a unique 64-character hexadecimal ADMIN_LOGIN_TOKEN in .env, then retry.\n");
    exit(1);
}

fwrite(STDOUT, $appUrl . '/admin-entry-' . substr($token, 0, 32) . '-' . substr($token, 32) . PHP_EOL);
