<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Delivery, returns and privacy';
$metaDesc = 'Delivery, returns, payments, privacy and terms of sale at Velora Bellmont.';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap narrow section prose">
    <h1>Delivery, returns and privacy</h1>

    <h2 id="delivery">Delivery</h2>
    <p>We deliver to every region in Ghana. Fees and timelines depend on your region, and delivery is free on orders over <?= e(money(cfg('shipping.free_over'))) ?>. See <a href="<?= e(url('delivery.php')) ?>">Delivery and shipping</a> for the full list.</p>

    <h2 id="returns">Returns and exchanges</h2>
    <p>Return unworn pieces in their original packaging within <?= (int) return_days() ?> days of delivery for a refund or an exchange. If a piece arrives damaged or is not what you ordered, tell us within 48 hours and we will replace it at no cost. Earrings can only be returned if they are unworn and sealed, for hygiene reasons. See <a href="<?= e(url('returns.php')) ?>">Returns and exchanges</a> for how to start a return.</p>

    <h2 id="materials">Materials</h2>
    <p>Finish names such as gold tone, rose gold tone and silver tone describe the colour of a piece, not the metal it is made of. We name a specific material only after confirming it with our supplier. See <a href="<?= e(url('care.php')) ?>">Materials and care</a>.</p>

    <h2 id="payments">Payments</h2>
    <p>You can pay by card or mobile money (MTN MoMo, Telecel Cash, AT Money) through Paystack, or pay on delivery in <?= e(cod_regions_text()) ?>. Card and mobile money details are entered on Paystack's secure page. They never reach our servers and we do not store them.</p>

    <h2 id="privacy">Privacy</h2>
    <p>We collect your name, email, phone number and delivery address so we can process and deliver your orders. We use them for that and for contacting you about your order. We do not sell your data. We share it only with the courier delivering your order and the payment provider processing your payment.</p>
    <p>We handle personal data in line with Ghana's Data Protection Act, 2012 (Act 843). You can ask us to see, correct or delete the personal data we hold about you by emailing <a href="mailto:<?= e(cfg('site.support_email')) ?>"><?= e(cfg('site.support_email')) ?></a>. Passwords are stored as one-way hashes, so nobody at Velora Bellmont can read them.</p>
    <p>If you write to us through the contact form, we store your name, email, phone number (if you give it), and your message so that we can reply.</p>
    <p>When you review a piece you bought, your review is shown with your first name and the first letter of your surname. If you add a photo, it is shown with the review. You can ask us to remove a review or photo at any time. Photos that customers send us or post on Instagram or TikTok appear in our gallery only when the customer has told us we may show them.</p>
    <h3 id="cookies">Analytics and cookies</h3>
    <p>We keep a simple, anonymous count of visits and page views to see how the shop is used. These counts use no cookies and hold no personal data, and are skipped if your browser sends a Do Not Track or Global Privacy Control signal.</p>
    <?php if (consent_needed()): ?>
    <p>We would also like to use <?= e(consent_services()) ?> to understand which pieces people look at and to show our ads to people who may like them. These services may set cookies. They load only if you choose Allow in the cookie banner, and they never load if your browser sends a Do Not Track or Global Privacy Control signal. You can change your mind at any time with Cookie choices at the bottom of every page.</p>
    <?php endif; ?>
    <?php if (trim((string) cfg('analytics.plausible_domain', '')) !== ''): ?><p>We use Plausible Analytics, which does not use cookies or collect personal data.</p><?php endif; ?>

    <h2 id="terms">Terms of sale</h2>
    <ol>
        <li><strong>Prices.</strong> Prices are in Ghana cedis (GHS). Delivery is charged separately unless your order qualifies for free delivery.</li>
        <li><strong>Orders.</strong> An order is confirmed once payment is received, or once you place a pay-on-delivery order. We may cancel an order if a piece is out of stock or a price was listed in error. If we do, we refund any payment in full.</li>
        <li><strong>Payment.</strong> Card and mobile money payments are taken by Paystack. Pay on delivery is available in <?= e(cod_regions_text()) ?> only. Please have the full amount ready for the courier.</li>
        <li><strong>Our pieces.</strong> We describe and photograph each piece as accurately as we can. Colours can look slightly different on different screens. If a listing does not match what you received, tell us within 48 hours and we will put it right.</li>
        <li><strong>Delivery.</strong> Delivery times are estimates. See <a href="<?= e(url('delivery.php')) ?>">Delivery and shipping</a>.</li>
        <li><strong>Returns.</strong> Our returns, exchanges and promise are set out on <a href="<?= e(url('returns.php')) ?>">Returns and exchanges</a>. Nothing here limits your rights under Ghanaian consumer law.</li>
        <li><strong>Gift cards.</strong> Gift cards are not refundable for cash.</li>
        <li><strong>Questions.</strong> Write to <a href="mailto:<?= e(cfg('site.support_email')) ?>"><?= e(cfg('site.support_email')) ?></a> and we will reply.</li>
    </ol>
    <?php if (policies_updated() !== ''): ?><p class="muted small">Last updated <?= e(policies_updated()) ?></p><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
