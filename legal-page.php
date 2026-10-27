<?php
require __DIR__ . '/includes/bootstrap.php';
$page = basename($_SERVER['SCRIPT_NAME'] ?? '');
$pages = [
    'privacy-policy.php' => ['Privacy policy', 'policy_privacy', 'We use your details only to process and deliver your order. We do not sell your personal information. Contact the store if you have a question about information associated with an order.'],
    'terms.php' => ['Terms and conditions', 'policy_terms', 'Orders are subject to product availability and confirmation by our team. Product prices and delivery charges are displayed in Sri Lankan Rupees and confirmed before an order is placed.'],
    'return-policy.php' => ['Return policy', 'policy_returns', 'Contact us within 7 days of delivery about an unused item in its original packaging. Items must be in saleable condition. We will confirm whether the item is eligible and explain the next steps.'],
];
[$pageTitle, $settingKey, $fallback] = $pages[$page] ?? $pages['terms.php'];
$policy = setting($pdo, $settingKey, $fallback);
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><span class="eyebrow">More & More</span><h1><?= h($pageTitle) ?></h1></section>
<article class="section-wrap editorial-copy policy-copy"><p><?= nl2br(h($policy)) ?></p><p>For questions about this policy, please <a href="contact.php">contact us</a>.</p><small>Last updated <?= date('F Y') ?></small></article>
<?php require __DIR__ . '/includes/footer.php'; ?>
