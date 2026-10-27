<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "PHP 8.1 or later is required.\n");
    exit(1);
}
require dirname(__DIR__) . '/config/database.php';
if (strlen(getenv('APP_KEY') ?: '') < 32 || str_starts_with((string) getenv('APP_KEY'), 'replace-with-')) {
    fwrite(STDERR, "Set a unique APP_KEY of at least 32 characters in .env before creating an administrator.\n");
    exit(1);
}

$count = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($count > 0) {
    fwrite(STDERR, "An administrator already exists. Refusing to create another initial admin.\n");
    exit(1);
}

$username = strtolower(trim((string) readline('Administrator username: ')));
if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/', $username)) {
    fwrite(STDERR, "Use a 3–32 character username with letters, numbers, dots, dashes or underscores.\n");
    exit(1);
}
$name = trim((string) readline('Administrator display name [' . $username . ']: ')) ?: $username;
if (strlen($name) > 120) {
    fwrite(STDERR, "Display name must be 120 characters or fewer.\n");
    exit(1);
}

$password = getenv('ADMIN_SETUP_PASSWORD') ?: (string) readline('Password (minimum 12 characters): ');
if (strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Password must contain 12–72 characters.\n");
    exit(1);
}

$statement = $pdo->prepare('INSERT INTO admin_users (name, username, email, password_hash) VALUES (?, ?, NULL, ?)');
$statement->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Administrator created for username {$username}.\n");
