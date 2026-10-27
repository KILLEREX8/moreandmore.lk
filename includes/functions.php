<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function setting(PDO $pdo, string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $query = $pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key = ?');
    $query->execute([$key]);
    $value = $query->fetchColumn();
    $cache[$key] = $value === false ? $default : (string) $value;
    return $cache[$key];
}

function save_setting(PDO $pdo, string $key, string $value): void
{
    $statement = $pdo->prepare(
        'INSERT INTO store_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $statement->execute([$key, $value]);
}

function money(int|float|string $amount): string
{
    return 'LKR ' . number_format((float) $amount, 2);
}

function product_price(array $product): int
{
    return isset($product['discounted_price']) && $product['discounted_price'] !== null
        ? (int) $product['discounted_price']
        : (int) $product['price'];
}

function order_statuses(): array
{
    return ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
}

function customer_order_status_description(string $status): string
{
    return match ($status) {
        'Pending' => 'We have received your order.',
        'Confirmed' => 'Your order has been confirmed.',
        'Processing' => 'We are preparing your package.',
        'Shipped' => 'Your package is on the way.',
        'Delivered' => 'Your package has been delivered.',
        'Cancelled' => 'This order has been cancelled.',
        default => 'Your order status has been updated.',
    };
}

function valid_email(?string $email): bool
{
    return $email === null || trim($email) === '' || filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

function valid_date(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function cart_items(PDO $pdo): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart) || $cart === []) {
        return [];
    }
    $ids = array_values(array_filter(array_map('intval', array_keys($cart)), static fn (int $id): bool => $id > 0));
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = $pdo->prepare("SELECT p.*, c.name AS category_name,
        (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC,pi.sort_order,pi.id LIMIT 1) AS image_path
        FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_published=1 AND c.is_active=1 AND p.id IN ({$placeholders})");
    $query->execute($ids);
    $rows = $query->fetchAll();
    $available = array_fill_keys(array_map(static fn (array $row): int => (int) $row['id'], $rows), true);
    foreach (array_keys($cart) as $id) {
        if (!isset($available[(int) $id])) {
            unset($_SESSION['cart'][$id]);
        }
    }
    foreach ($rows as &$row) {
        $row['quantity'] = min(99, max(1, (int) ($cart[$row['id']] ?? 1)));
    }
    return $rows;
}

function cart_count(): int
{
    $count = 0;
    foreach ($_SESSION['cart'] ?? [] as $quantity) {
        $count += max(0, (int) $quantity);
    }
    return $count;
}

function upload_product_image(array $file, ?string $currentPath = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentPath;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image upload failed. Choose another image.');
    }
    $maxBytes = max(1024, (int) (getenv('UPLOAD_MAX_BYTES') ?: 5242880));
    if (($file['size'] ?? 0) > $maxBytes || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('Image must be no larger than 5 MB.');
    }
    $info = new finfo(FILEINFO_MIME_TYPE);
    $mime = $info->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!isset($extensions[$mime]) || $imageInfo === false || $imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] > 6000 || $imageInfo[1] > 6000 || $imageInfo[0] * $imageInfo[1] > 30000000) {
        throw new RuntimeException('Upload a valid JPG, PNG, or WebP image.');
    }
    $directory = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The product image directory is unavailable.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The image could not be saved.');
    }
    return 'uploads/products/' . $filename;
}

function delete_local_upload(?string $path): void
{
    if (!$path || !preg_match('#^uploads/products/[a-f0-9]{40}\.(jpg|png|webp)$#', $path)) {
        return;
    }
    $file = dirname(__DIR__) . '/' . $path;
    if (is_file($file)) {
        unlink($file);
    }
}

function site_url(string $path = ''): string
{
    $base = rtrim(getenv('APP_URL') ?: '', '/');
    return $base . '/' . ltrim($path, '/');
}

function whatsapp_contact_url(string $phoneNumber): ?string
{
    $digits = preg_replace('/\D+/', '', $phoneNumber);
    if (!is_string($digits) || strlen($digits) < 8 || strlen($digits) > 15) {
        return null;
    }
    return 'https://wa.me/' . $digits;
}
