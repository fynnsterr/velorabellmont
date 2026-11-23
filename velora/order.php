<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$order = user_order(val('ref'), $user);
if (!$order && !$user) {
    flash('info', 'Enter your order reference and email to view this order.');
    redirect('track.php?ref=' . urlencode(val('ref')));
}
if (!$order) {
    render_error_page(404, 'We could not find that order', 'Check the link, or open your orders from the menu.');
}

$items = order_items_for((int) $order['id']);
$wa = whatsapp_link($order, $items);
$isGift = $order['kind'] === 'giftcard';
$cards = $isGift ? gift_cards_for_order((int) $order['id']) : [];
$earnable = !$isGift && $order['user_id'] !== null ? points_for_amount((float) $order['subtotal'] - (float) $order['discount'] - (float) $order['points_discount']) : 0;
$timeline = order_timeline($order, order_events((int) $order['id']));

$canPay = $order['payment_method'] === 'paystack' && in_array($order['payment_status'], ['pending', 'failed'], true) && $order['order_status'] !== 'cancelled';
$pageTitle = 'Order ' . $order['order_ref'];
require __DIR__ . '/includes/header.php';
$step = $order['order_status'] === 'cancelled' ? 2 : 3;
require __DIR__ . '/includes/steps.php';
?>
<section class="wrap section">
    <?php if (!$isGift && (order_is_paid($order) || $order['payment_method'] === 'cod') && empty($_SESSION['vb_purchase_tracked'][$order['order_ref']])): $_SESSION['vb_purchase_tracked'][$order['order_ref']] = 1; ?>
        <span hidden data-track="purchase" data-ref="<?= e($order['order_ref']) ?>" data-value="<?= e(number_format((float) $order['total'], 2, '.', '')) ?>"></span>
    <?php endif; ?>
    <div class="page-head">
        <h1>Order <?= e($order['order_ref']) ?></h1>
        <p class="muted">Placed <?= e(date('j F Y, g:i a', strtotime($order['created_at']))) ?></p>
    </div>

    <div class="cart-layout">
        <div>
            <div class="status-row">
                <p>Order status <?= badge($order['order_status']) ?></p>
                <p>Payment <?= pay_badge($order['payment_status']) ?> <span class="muted small"><?= e(pay_label($order)) ?></span></p>
            </div>

            <?php if (!$isGift && $order['order_status'] !== 'cancelled'): ?>
                <ol class="timeline" aria-label="Order progress">
                    <?php foreach ($timeline as $step): ?>
                        <li class="tl<?= $step['state'] === 'done' ? ' is-done' : '' ?><?= $step['state'] === 'current' ? ' is-current' : '' ?>"><span class="tl-dot" aria-hidden="true"></span><span class="tl-label"><?= e($step['label']) ?><?php if ($step['state'] === 'done' && !empty($step['at'])): ?><span class="tl-when"><?= e(date('j M, g:i a', strtotime((string) $step['at']))) ?></span><?php endif; ?></span></li>
                    <?php endforeach; ?>
                </ol>
                <?php if (!empty($order['courier'])): ?>
                    <p class="eta">Courier: <strong><?= e($order['courier']) ?></strong><?php if (!empty($order['tracking_no'])): ?> · Tracking number <strong><?= e($order['tracking_no']) ?></strong><?php endif; ?><?php if (!empty($order['tracking_url'])): ?> · <a class="text-link" href="<?= e($order['tracking_url']) ?>" rel="noopener" target="_blank">Track with the courier</a><?php endif; ?></p>
                <?php endif; ?>
            <?php elseif (!$isGift): ?>
                <p class="flash flash-error">This order was cancelled.</p>
            <?php endif; ?>

            <?php if ($isGift && order_is_paid($order)): ?>
                <?php foreach ($cards as $gc): ?>
                    <div class="gift-code-box">
                        <p class="eyebrow">Your gift card</p>
                        <p class="gift-code"><?= e($gc['code']) ?></p>
                        <p><?= e(money($gc['amount'])) ?><?= !empty($gc['recipient_email']) ? ' · emailed to ' . e($gc['recipient_email']) : ' · send this code to the person you are gifting' ?></p>
                    </div>
                <?php endforeach; ?>
            <?php elseif ($isGift): ?>
                <p class="eta">Your gift card code appears here and in your email as soon as payment is confirmed.</p>
            <?php endif; ?>

            <?php if (!$isGift && $earnable > 0 && in_array($order['payment_status'], ['pending', 'failed'], true) && $order['order_status'] !== 'cancelled'): ?>
                <p class="eta">You will earn <strong><?= (int) $earnable ?> reward points</strong> once payment is confirmed.</p>
            <?php elseif (!$isGift && $earnable > 0 && order_is_paid($order) && $order['order_status'] !== 'cancelled'): ?>
                <p class="eta">You earned <strong><?= (int) $earnable ?> reward points</strong> on this order. <a class="text-link" href="<?= e(url('rewards.php')) ?>">See your rewards</a></p>
            <?php endif; ?>

            <?php if (!$isGift && !empty($order['delivery_estimate']) && $order['order_status'] !== 'cancelled' && $order['order_status'] !== 'delivered'): ?>
                <p class="eta"><strong>Estimated delivery</strong><br><?= e($order['delivery_estimate']) ?>, counted from when you ordered. We will call <?= e($order['phone']) ?> before we arrive.</p>
            <?php endif; ?>

            <?php if ($canPay): ?>
                <form method="post" action="<?= e(url('pay.php')) ?>" class="pay-again">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ref" value="<?= e($order['order_ref']) ?>">
                    <p>This order is waiting for payment.</p>
                    <button class="btn btn-primary" type="submit"><?= $order['payment_status'] === 'failed' ? 'Try payment again' : 'Complete payment' ?></button>
                </form>
            <?php endif; ?>

            <h2>Items</h2>
            <ul class="line-list">
                <?php foreach ($items as $it): ?>
                    <li class="line line-simple">
                        <div class="line-main">
                            <p class="line-name"><?= e($it['product_name']) ?></p>
                            <p class="muted"><?= !empty($it['size']) ? 'Size ' . e($it['size']) . ' · ' : '' ?><?= (int) $it['quantity'] ?> × <?= e(money($it['unit_price'])) ?></p>
                        </div>
                        <p class="line-total"><?= e(money((float) $it['unit_price'] * (int) $it['quantity'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (!$isGift): ?>
            <h2>Delivering to</h2>
            <address class="address">
                <?= e($order['full_name']) ?><br>
                <?= e($order['address']) ?><br>
                <?= e($order['city']) ?>, <?= e($order['region']) ?><br>
                <?= e($order['phone']) ?>
            </address>
            <?php if (!empty($order['notes'])): ?><p class="muted">Notes: <?= e($order['notes']) ?></p><?php endif; ?>
            <?php endif; ?>
        </div>

        <aside class="summary" aria-label="Order totals">
            <h2>Totals</h2>
            <dl class="sum-rows">
                <div><dt>Subtotal</dt><dd><?= e(money($order['subtotal'])) ?></dd></div>
                <?php if ((float) ($order['discount'] ?? 0) > 0): ?>
                    <div><dt>Discount<?= !empty($order['promo_code']) ? ' (' . e($order['promo_code']) . ')' : '' ?></dt><dd>-<?= e(money($order['discount'])) ?></dd></div>
                <?php endif; ?>
                <?php if ((int) $order['points_used'] > 0): ?>
                    <div><dt>Points (<?= (int) $order['points_used'] ?>)</dt><dd>-<?= e(money($order['points_discount'])) ?></dd></div>
                <?php endif; ?>
                <?php if (!$isGift): ?><div><dt>Delivery</dt><dd><?= (float) $order['shipping'] > 0 ? e(money($order['shipping'])) : 'Free' ?></dd></div><?php endif; ?>
                <?php if ((float) $order['gift_amount'] > 0): ?>
                    <div><dt>Gift card</dt><dd>-<?= e(money($order['gift_amount'])) ?></dd></div>
                <?php endif; ?>
                <div class="sum-total"><dt>Total</dt><dd><?= e(money($order['total'])) ?></dd></div>
            </dl>
            <?php if (!$isGift): ?><p><a class="btn btn-block" href="<?= e($wa) ?>" target="_blank" rel="noopener">Send this order on WhatsApp</a></p><?php endif; ?>
            <p class="muted small">Opens a WhatsApp chat with us, with your order details ready to send. A confirmation email is also sent to <?= e($order['email']) ?> where our mail service is set up.</p>
            <p><a class="text-link" href="<?= e(url('shop.php')) ?>">Keep shopping</a></p>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
