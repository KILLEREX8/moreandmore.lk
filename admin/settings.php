<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$adminPageTitle = 'Store settings';
$fields = [
    'site_name' => 'Website name',
    'contact_phone' => 'Contact phone number',
    'whatsapp_number' => 'WhatsApp number',
    'contact_email' => 'Email address',
    'store_address' => 'Store address',
    'promo_message' => 'Promotional message',
    'hero_title' => 'Homepage banner title',
    'hero_subtitle' => 'Homepage banner subtitle',
    'social_instagram' => 'Instagram URL',
    'social_facebook' => 'Facebook URL',
    'delivery_service_areas' => 'Delivery service areas (comma-separated districts or Islandwide)',
    'policy_privacy' => 'Privacy policy',
    'policy_terms' => 'Terms and conditions',
    'policy_returns' => 'Return policy',
];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [];
    foreach ($fields as $key => $label) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
        if (strlen($values[$key]) > ($key === 'policy_privacy' || $key === 'policy_terms' || $key === 'policy_returns' ? 10000 : 1000)) $errors[] = $label . ' is too long.';
    }
    if ($values['site_name'] !== 'More & More') $errors[] = 'The website name is always More & More.';
    if ($values['whatsapp_number'] !== '' && whatsapp_contact_url($values['whatsapp_number']) === null) {
        $errors[] = 'Enter a WhatsApp number with country code and 8–15 digits.';
    }
    if ($values['contact_email'] !== '' && !filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid contact email.';
    foreach (['social_instagram','social_facebook'] as $urlKey) {
        if ($values[$urlKey] !== '' && filter_var($values[$urlKey], FILTER_VALIDATE_URL) === false) $errors[] = 'Social links must be valid URLs.';
        if ($values[$urlKey] !== '' && !str_starts_with($values[$urlKey], 'https://')) $errors[] = 'Social links must use HTTPS.';
    }
    $delivery = filter_var($_POST['delivery_default_charge'] ?? null, FILTER_VALIDATE_INT);
    $threshold = filter_var($_POST['delivery_free_threshold'] ?? 0, FILTER_VALIDATE_INT);
    $lowStock = filter_var($_POST['low_stock_threshold'] ?? 5, FILTER_VALIDATE_INT);
    if ($delivery === false || $delivery < 0 || $delivery > 10000000) $errors[] = 'Delivery charge must be a non-negative whole number.';
    if ($threshold === false || $threshold < 0 || $threshold > 100000000) $errors[] = 'Free-delivery threshold must be a non-negative whole number.';
    if ($lowStock === false || $lowStock < 0 || $lowStock > 1000000) $errors[] = 'Low-stock threshold is invalid.';
    $newLogo = null;
    $newHero = null;
    $oldLogo = setting($pdo, 'site_logo');
    $oldHero = setting($pdo, 'hero_image');
    if (!$errors) {
        try {
            $newLogo = upload_product_image($_FILES['site_logo'] ?? [], setting($pdo, 'site_logo'));
            $newHero = upload_product_image($_FILES['hero_banner'] ?? [], setting($pdo, 'hero_image'));
            $pdo->beginTransaction();
            foreach ($values as $key => $value) save_setting($pdo, $key, $value);
            save_setting($pdo, 'currency', 'LKR');
            save_setting($pdo, 'delivery_default_charge', (string) $delivery);
            save_setting($pdo, 'delivery_free_threshold', (string) $threshold);
            save_setting($pdo, 'delivery_enabled', isset($_POST['delivery_enabled']) ? '1' : '0');
            save_setting($pdo, 'cod_enabled', isset($_POST['cod_enabled']) ? '1' : '0');
            save_setting($pdo, 'low_stock_threshold', (string) $lowStock);
            if ($newLogo !== null) save_setting($pdo, 'site_logo', $newLogo);
            if ($newHero !== null) save_setting($pdo, 'hero_image', $newHero);
            $pdo->commit();
            if ($newLogo !== $oldLogo) delete_local_upload($oldLogo);
            if ($newHero !== $oldHero) delete_local_upload($oldHero);
            flash('success', 'Store settings saved.');
            redirect('settings.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($newLogo && $newLogo !== setting($pdo, 'site_logo')) {
                $file = dirname(__DIR__) . '/' . $newLogo;
                if (is_file($file)) unlink($file);
            }
            if ($newHero && $newHero !== setting($pdo, 'hero_image')) {
                $file = dirname(__DIR__) . '/' . $newHero;
                if (is_file($file)) unlink($file);
            }
            error_log('Store settings update failed: ' . $exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Settings could not be saved.';
        }
    }
}
require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-title"><div><h1>Store settings</h1><p>Manage public store details, promotions, delivery and policies.</p></div></div>
<?php if ($errors): ?><div class="notice notice-error"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>
<section class="admin-panel"><form method="post" enctype="multipart/form-data" class="admin-form"><?= csrf_field() ?>
<?php foreach (array_slice($fields,0,8,true) as $key => $label): ?><label <?= in_array($key,['store_address','promo_message','hero_title','hero_subtitle'],true) ? 'class="wide"' : '' ?>><?= h($label) ?><?php if (in_array($key,['store_address','promo_message','hero_title','hero_subtitle'],true)): ?><textarea name="<?= h($key) ?>" rows="<?= $key === 'store_address' ? 2 : 3 ?>"><?= h($_POST[$key] ?? setting($pdo,$key,$key==='site_name'?'More & More':'')) ?></textarea><?php else: ?><input name="<?= h($key) ?>" <?= $key === 'whatsapp_number' ? 'type="tel" placeholder="+94 77 123 4567"' : '' ?> maxlength="1000" value="<?= h($_POST[$key] ?? setting($pdo,$key,$key==='site_name'?'More & More':'')) ?>"><?php if ($key === 'whatsapp_number'): ?><small>Include your country code, for example +94 for Sri Lanka. Leave blank to hide the WhatsApp button.</small><?php endif; ?><?php endif; ?></label><?php endforeach; ?>
<label>Website logo<input type="file" name="site_logo" accept="image/jpeg,image/png,image/webp"><?php if (setting($pdo,'site_logo')): ?><img class="thumb" src="../<?= h(setting($pdo,'site_logo')) ?>" alt="Current logo"><?php endif; ?></label>
<label>Homepage banner image<input type="file" name="hero_banner" accept="image/jpeg,image/png,image/webp"><?php if (setting($pdo,'hero_image')): ?><img class="thumb" src="../<?= h(setting($pdo,'hero_image')) ?>" alt="Current banner"><?php endif; ?></label>
<?php foreach (array_slice($fields,8,null,true) as $key => $label): ?><label <?= str_starts_with($key,'policy_') || $key==='delivery_service_areas' ? 'class="wide"' : '' ?>><?= h($label) ?><?php if (str_starts_with($key,'policy_') || $key==='delivery_service_areas'): ?><textarea name="<?= h($key) ?>" rows="<?= str_starts_with($key,'policy_') ? 5 : 2 ?>"><?= h($_POST[$key] ?? setting($pdo,$key)) ?></textarea><?php else: ?><input name="<?= h($key) ?>" type="<?= str_starts_with($key,'social_') ? 'url' : 'text' ?>" maxlength="1000" value="<?= h($_POST[$key] ?? setting($pdo,$key)) ?>"><?php endif; ?></label><?php endforeach; ?>
<label>Delivery charge (LKR)<input type="number" min="0" name="delivery_default_charge" value="<?= h($_POST['delivery_default_charge'] ?? setting($pdo,'delivery_default_charge','500')) ?>"></label>
<label>Free delivery threshold (LKR)<input type="number" min="0" name="delivery_free_threshold" value="<?= h($_POST['delivery_free_threshold'] ?? setting($pdo,'delivery_free_threshold','0')) ?>"></label>
<label>Low-stock alert threshold<input type="number" min="0" name="low_stock_threshold" value="<?= h($_POST['low_stock_threshold'] ?? setting($pdo,'low_stock_threshold','5')) ?>"></label>
<label>Currency<input value="Sri Lankan Rupees (LKR)" disabled></label>
<label class="checkbox-label"><input type="checkbox" name="delivery_enabled" value="1" <?= ($_POST ? isset($_POST['delivery_enabled']) : setting($pdo,'delivery_enabled','1')==='1') ? 'checked' : '' ?>> Enable delivery charges</label>
<label class="checkbox-label"><input type="checkbox" name="cod_enabled" value="1" <?= ($_POST ? isset($_POST['cod_enabled']) : setting($pdo,'cod_enabled','1')==='1') ? 'checked' : '' ?>> Enable cash on delivery</label>
<button class="button button-dark">Save settings</button></form></section>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
