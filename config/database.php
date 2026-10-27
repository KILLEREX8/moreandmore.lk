<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/environment.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'more_and_more';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    error_log('The PDO MySQL extension is not enabled.');
    http_response_code(503);
    exit('The store requires the PHP PDO MySQL extension.');
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset={$charset}",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]
    );
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The store is temporarily unavailable. Please try again later.');
}
