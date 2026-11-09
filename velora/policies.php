<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Delivery, returns and privacy';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap narrow section prose">
    <h1>Delivery, returns and privacy</h1>

    <h2 id="delivery">Delivery</h2>
    <p>We deliver to every region in Ghana. Shipping is a flat <?= e(money(cfg('shipping.flat'))) ?>, and free when your order is over <?= e(money(cfg('shipping.free_over'))) ?>. Orders in Greater Accra usually arrive in 1 to 3 working days. Other regions take 3 to 6 working days. A courier will call the phone number on your order before delivery.</p>

    <h2 id="returns">Returns and exchanges</h2>
    <p>If you are not happy, return unworn pieces in their original packaging within 7 days of delivery and we will refund you or exchange the piece. Earrings can only be returned if they are unworn and sealed, for hygiene reasons. If a piece arrives damaged or is not what you ordered, tell us within 48 hours and we will replace it at no cost.</p>
    <p>Our pieces are gold tone, rose gold tone and silver tone plated jewelry. They are not solid precious metal. Keep them dry and away from perfume to make the finish last.</p>

    <h2 id="payments">Payments</h2>
    <p>You can pay by card or mobile money (MTN MoMo, Telecel Cash, AT Money) through Paystack, or pay on delivery in Greater Accra and Ashanti. Card and mobile money details are entered on Paystack's secure page. They never reach our servers and we do not store them.</p>

    <h2 id="privacy">Privacy</h2>
    <p>We collect your name, email, phone number and delivery address so we can process and deliver your orders. We use them for that and for contacting you about your order. We do not sell your data. We share it only with the courier delivering your order and the payment provider processing your payment.</p>
    <p>We handle personal data in line with Ghana's Data Protection Act, 2012 (Act 843). You can ask us to see, correct or delete the personal data we hold about you by emailing <a href="mailto:<?= e(cfg('site.support_email')) ?>"><?= e(cfg('site.support_email')) ?></a>. Passwords are stored as one-way hashes, so nobody at Velora Bellmont can read them.</p>

    <h2 id="terms">Terms of sale</h2>
    <p>Prices are in Ghana cedis (GHS). An order is confirmed once payment is received, or once you place a pay-on-delivery order. We hold the right to cancel an order if a piece is out of stock or a price was listed in error, and we will refund any payment in full.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
