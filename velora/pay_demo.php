<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
if (paystack_enabled()) {
    redirect('account.php');
}

$ref = val('ref');
$order = user_order($ref, $user);
if (!$order || $order['payment_method'] !== 'paystack' || $order['payment_status'] === 'paid' || $order['order_status'] === 'cancelled') {
    flash('error', 'That order cannot be paid for.');
    redirect('account.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $outcome = val('outcome');
    if ($outcome === 'success') {
        mark_order_paid((int) $order['id'], 'DEMO-' . $order['order_ref']);
        flash('success', 'Payment received. Thank you for your order.');
    } else {
        db()->prepare("UPDATE vb_orders SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'")->execute([(int) $order['id']]);
        flash('error', 'The payment did not go through. You can try again from your order.');
    }
    redirect('order.php?ref=' . urlencode($order['order_ref']));
}

$pageTitle = 'Pay for your order';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap narrow section">
    <h1>Pay for order <?= e($order['order_ref']) ?></h1>
    <p class="flash flash-info">Demo payment page. No money moves here. Add your Paystack keys in config.php and this page is replaced by the real Paystack checkout.</p>
    <div class="summary">
        <dl class="sum-rows">
            <div><dt>Order</dt><dd><?= e($order['order_ref']) ?></dd></div>
            <div class="sum-total"><dt>Amount due</dt><dd><?= e(money($order['total'])) ?></dd></div>
        </dl>
        <form method="post" action="<?= e(url('pay_demo.php')) ?>" class="demo-actions">
            <?= csrf_field() ?>
            <input type="hidden" name="ref" value="<?= e($order['order_ref']) ?>">
            <button class="btn btn-primary btn-block" type="submit" name="outcome" value="success">Simulate successful payment</button>
            <button class="btn btn-block" type="submit" name="outcome" value="failed">Simulate failed payment</button>
        </form>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
