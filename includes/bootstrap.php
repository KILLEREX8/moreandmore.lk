<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/environment.php';
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', getenv('APP_DEBUG') === '1' && getenv('APP_ENV') !== 'production' ? '1' : '0');
start_secure_session();
$appKey = getenv('APP_KEY') ?: '';
if (strlen($appKey) < 32 || str_starts_with($appKey, 'replace-with-')) {
    http_response_code(500);
    exit('Set a unique APP_KEY of at least 32 characters in the environment.');
}
if (getenv('APP_ENV') === 'production') {
    if (getenv('SESSION_SECURE') !== '1' || getenv('APP_DEBUG') === '1') {
        http_response_code(500);
        exit('Production security settings are incomplete.');
    }
}
require dirname(__DIR__) . '/config/database.php';
if (str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/')) {
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}
foreach (['pdo_mysql', 'fileinfo', 'mbstring'] as $requiredExtension) {
    if (!extension_loaded($requiredExtension)) {
        error_log('Required PHP extension is unavailable: ' . $requiredExtension);
        http_response_code(500);
        exit('A required PHP extension is unavailable. Check the README setup instructions.');
    }
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
