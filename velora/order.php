<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$order = user_order(val('ref'), $user);
if (!$order) {
    render_error_page(404, 'We could not find that order', 'Check the link, or open your orders from the menu.');
}

$st = db()->prepare('SELECT * FROM vb_order_items WHERE order_id = ? ORDER BY id');
$st->execute([(int) $order['id']]);
$items = $st->fetchAll();

$canPay = $order['payment_method'] === 'paystack' && $order['payment_status'] !== 'paid' && $order['order_status'] !== 'cancelled';
$pageTitle = 'Order ' . $order['order_ref'];
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section">
    <div class="page-head">
        <h1>Order <?= e($order['order_ref']) ?></h1>
        <p class="muted">Placed <?= e(date('j F Y, g:i a', strtotime($order['created_at']))) ?></p>
    </div>

    <div class="cart-layout">
        <div>
            <div class="status-row">
                <p>Order status <?= badge($order['order_status']) ?></p>
                <p>Payment <?= badge($order['payment_status']) ?> <span class="muted small"><?= $order['payment_method'] === 'cod' ? 'Pay on delivery' : 'Card or mobile money' ?></span></p>
            </div>

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
                            <p class="muted"><?= (int) $it['quantity'] ?> × <?= e(money($it['unit_price'])) ?></p>
                        </div>
                        <p class="line-total"><?= e(money((float) $it['unit_price'] * (int) $it['quantity'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h2>Delivering to</h2>
            <address class="address">
                <?= e($order['full_name']) ?><br>
                <?= e($order['address']) ?><br>
                <?= e($order['city']) ?>, <?= e($order['region']) ?><br>
                <?= e($order['phone']) ?>
            </address>
            <?php if (!empty($order['notes'])): ?><p class="muted">Notes: <?= e($order['notes']) ?></p><?php endif; ?>
        </div>

        <aside class="summary" aria-label="Order totals">
            <h2>Totals</h2>
            <dl class="sum-rows">
                <div><dt>Subtotal</dt><dd><?= e(money($order['subtotal'])) ?></dd></div>
                <div><dt>Shipping</dt><dd><?= (float) $order['shipping'] > 0 ? e(money($order['shipping'])) : 'Free' ?></dd></div>
                <div class="sum-total"><dt>Total</dt><dd><?= e(money($order['total'])) ?></dd></div>
            </dl>
            <p><a class="text-link" href="<?= e(url('shop.php')) ?>">Keep shopping</a></p>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
