<?php
declare(strict_types=1);

function valid_username(string $username): bool
{
    return preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/', $username) === 1;
}

function current_customer(): ?array
{
    global $pdo;
    $customerId = (int) ($_SESSION['customer_id'] ?? 0);
    if ($customerId < 1) {
        return null;
    }

    $query = $pdo->prepare('SELECT id,username,full_name,phone FROM customers WHERE id=? AND is_active=1');
    $query->execute([$customerId]);
    $customer = $query->fetch();
    if (!$customer) {
        unset($_SESSION['customer_id'], $_SESSION['customer_username']);
        return null;
    }
    $_SESSION['customer_username'] = $customer['username'];
    return $customer;
}

function require_customer(): array
{
    $customer = current_customer();
    if ($customer === null) {
        flash('error', 'Sign in to view your account.');
        redirect('account-login.php');
    }
    return $customer;
}

function customer_login_ip_hash(): string
{
    return hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), getenv('APP_KEY') ?: '');
}

function customer_login_is_locked(PDO $pdo, string $ipHash): bool
{
    $pdo->exec('DELETE FROM customer_login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 30 DAY');
    $query = $pdo->prepare('SELECT COUNT(*) FROM customer_login_attempts WHERE ip_hash=? AND was_successful=0 AND attempted_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE');
    $query->execute([$ipHash]);
    return (int) $query->fetchColumn() >= 5;
}

function record_customer_login_attempt(PDO $pdo, string $ipHash, bool $successful): void
{
    $query = $pdo->prepare('INSERT INTO customer_login_attempts (ip_hash,was_successful) VALUES (?,?)');
    $query->execute([$ipHash, $successful ? 1 : 0]);
}
