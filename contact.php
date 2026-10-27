<?php
require __DIR__ . '/includes/bootstrap.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($name === '' || strlen($name) > 160) $errors[] = 'Enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) $errors[] = 'Enter a valid email address.';
    if (strlen($phone) > 32 || strlen($subject) < 3 || strlen($subject) > 180 || strlen($message) < 10 || strlen($message) > 5000) $errors[] = 'Please check the subject and message length.';
    if (!$errors) {
        $query = $pdo->prepare('INSERT INTO contact_messages (name,email,phone,subject,message) VALUES (?,?,?,?,?)');
        $query->execute([$name, $email, $phone ?: null, $subject, $message]);
        flash('success', 'Thank you. Your message has been received.');
        redirect('contact.php');
    }
}
$pageTitle = 'Contact us';
$whatsappUrl = whatsapp_contact_url(setting($pdo, 'whatsapp_number'));
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><span class="eyebrow">We are here to help</span><h1>Contact us</h1><p>Questions about an order or product? Send our team a message.</p></section>
<section class="section-wrap contact-layout"><div><h2>Get in touch</h2><p><?= h(setting($pdo, 'store_address', 'Sri Lanka')) ?></p><p><a href="tel:<?= h(setting($pdo, 'contact_phone', '')) ?>"><?= h(setting($pdo, 'contact_phone', 'Phone details coming soon')) ?></a></p><p><a href="mailto:<?= h(setting($pdo, 'contact_email', '')) ?>"><?= h(setting($pdo, 'contact_email', 'Email details coming soon')) ?></a></p><?php if ($whatsappUrl !== null): ?><p><a href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">Message us on WhatsApp</a></p><?php endif; ?></div>
<form class="form-panel" method="post"><?= csrf_field() ?><?php if ($errors): ?><div class="notice notice-error"><?= h(implode(' ', $errors)) ?></div><?php endif; ?><div class="form-grid"><label>Name<input name="name" maxlength="160" value="<?= h($_POST['name'] ?? '') ?>" required></label><label>Email<input name="email" type="email" maxlength="254" value="<?= h($_POST['email'] ?? '') ?>" required></label><label>Phone <small>(optional)</small><input name="phone" maxlength="32" value="<?= h($_POST['phone'] ?? '') ?>"></label><label>Subject<input name="subject" maxlength="180" value="<?= h($_POST['subject'] ?? '') ?>" required></label><label class="wide">Message<textarea name="message" rows="6" maxlength="5000" required><?= h($_POST['message'] ?? '') ?></textarea></label><button class="button button-dark">Send message</button></div></form></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
