</main>
<footer class="site-footer">
  <div class="footer-grid">
    <div><a class="brand brand-footer" href="index.php">More & More</a><p>Considered essentials for living well, delivered across Sri Lanka.</p></div>
    <div><h3>Explore</h3><a href="products.php">All products</a><a href="contact.php">Contact us</a><a href="faq.php">FAQ</a></div>
    <div><h3>Customer care</h3><a href="track-order.php">Track an order</a><a href="return-policy.php">Returns</a><a href="privacy-policy.php">Privacy</a><a href="terms.php">Terms</a></div>
    <div><h3>Get in touch</h3><a href="tel:<?= h(setting($pdo, 'contact_phone', '')) ?>"><?= h(setting($pdo, 'contact_phone', 'Contact us')) ?></a><a href="mailto:<?= h(setting($pdo, 'contact_email', '')) ?>"><?= h(setting($pdo, 'contact_email', 'Email our team')) ?></a><span><?= h(setting($pdo, 'store_address', 'Sri Lanka')) ?></span><?php foreach (['Instagram'=>'social_instagram','Facebook'=>'social_facebook'] as $label => $key): $socialUrl=setting($pdo,$key); if ($socialUrl): ?><a href="<?= h($socialUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h($label) ?> ↗</a><?php endif; endforeach; ?></div>
  </div>
  <div class="footer-bottom"><span>© <?= date('Y') ?> <?= h($siteName) ?>. All rights reserved.</span><span>Made for everyday living in Sri Lanka · LKR</span></div>
</footer>
<?php $whatsappUrl = whatsapp_contact_url(setting($pdo, 'whatsapp_number')); ?>
<?php if ($whatsappUrl !== null): ?><a class="whatsapp-float" href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Chat with More & More on WhatsApp"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 3.5A11.8 11.8 0 0 0 2 17.7L.5 23.5l6-1.6a11.8 11.8 0 0 0 5.7 1.5h.1A11.8 11.8 0 0 0 20.5 3.5ZM12.3 21.4a9.9 9.9 0 0 1-5-1.4l-.4-.2-3.6 1 1-3.5-.2-.4a9.8 9.8 0 1 1 8.2 4.5Zm5.4-7.4c-.3-.2-1.8-.9-2.1-1-.3-.1-.5-.2-.7.2-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.2-1.3-.5-2.5-1.5-.9-.8-1.5-1.8-1.7-2-.2-.3 0-.4.1-.6.1-.1.3-.3.4-.5.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.2-.7-1.7-1-2.3-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4s-1 1-1 2.4 1 2.8 1.1 3c.1.2 2 3.1 4.8 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.6-.1 1.8-.7 2-1.4.3-.7.3-1.3.2-1.4 0-.2-.2-.3-.5-.4Z"/></svg><span>Chat on WhatsApp</span></a><?php endif; ?>
<script src="assets/js/main.js" defer></script>
</body>
</html>
