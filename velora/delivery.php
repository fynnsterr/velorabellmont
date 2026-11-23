<?php
require __DIR__ . '/includes/bootstrap.php';

$zones = delivery_zones();
$free = (float) cfg('shipping.free_over');
$pageTitle = 'Delivery and shipping';
$metaDesc = 'Delivery fees and timelines for every region in Ghana, free delivery over ' . money($free) . ', tracking and pay on delivery.';
$jsonld = [breadcrumb_jsonld([['Home', abs_url('')], ['Delivery', abs_url('delivery.php')]])];
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section page-content prose">
    <p class="eyebrow eyebrow-dark">Delivery</p>
    <h1>Delivery and shipping</h1>
    <p class="lede">We deliver to every region in Ghana. Delivery is free on orders over <?= e(money($free)) ?>.</p>

    <h2 id="fees">Fees and timelines</h2>
    <p>Timelines are in working days (Monday to Friday) from the day we dispatch your order. Checkout shows your exact fee and delivery dates before you pay.</p>
    <ul class="zone-list">
        <?php foreach ($zones as $z): ?>
            <li>
                <strong><?= e($z['name']) ?></strong>
                <span><?= $z['days'][0] === $z['days'][1] ? (int) $z['days'][0] : (int) $z['days'][0] . ' to ' . (int) $z['days'][1] ?> working days</span>
                <span><?= e(money($z['fee'])) ?><?= $free > 0 ? ' (free over ' . e(money($free)) . ')' : '' ?></span>
            </li>
        <?php endforeach; ?>
    </ul>

    <h2 id="steps">From order to your door</h2>
    <ol class="steps-list">
        <li><strong>Order received.</strong> You get a confirmation email straight away.</li>
        <li><strong>Payment confirmed.</strong> For card and mobile money, once Paystack confirms. Pay on delivery orders are confirmed when you place them.</li>
        <li><strong>Processing.</strong> We check and pack your pieces.</li>
        <li><strong>Dispatched.</strong> We email you the courier and tracking number.</li>
        <li><strong>Out for delivery.</strong> The courier will call the phone number on your order.</li>
        <li><strong>Delivered.</strong> You get a delivery confirmation, and later a request to review your pieces.</li>
    </ol>
    <p>Follow every step with <a href="<?= e(url('track.php')) ?>">Track an order</a>, or from your account if you are logged in.</p>

    <h2 id="cod">Pay on delivery</h2>
    <p>Pay on delivery is available in <?= e(cod_regions_text()) ?>. You pay the courier in cash or mobile money when your order arrives. Please have your phone on so the courier can reach you.</p>

    <h2 id="address">Before you order</h2>
    <ul>
        <li>Use a phone number that is on and reachable. Couriers call before delivery.</li>
        <li>Give a clear address with a landmark where you can.</li>
        <li>If a courier cannot reach you after several attempts, we will contact you to arrange another delivery, which may mean paying the delivery fee again.</li>
    </ul>

    <h2 id="estimates">Delivery dates are estimates</h2>
    <p>The days above are our best estimate from the day we dispatch. Weather, road conditions and courier delays can add time. If your order is more than a few days late, contact us and we will chase the courier.</p>

    <h2 id="problems">If something is wrong</h2>
    <p>If your order is late, damaged or not what you ordered, <a href="<?= e(url('contact.php')) ?>">contact us</a> with your order number. See <a href="<?= e(url('returns.php')) ?>">Returns and exchanges</a> for damaged or wrong items.</p>
<?php if (policies_updated() !== ''): ?>    <p class="muted small">Last updated <?= e(policies_updated()) ?></p>
<?php endif; ?></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
