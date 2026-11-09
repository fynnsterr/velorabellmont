<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$id = (int) val('id');
$st = db()->prepare('SELECT * FROM vb_orders WHERE id = ?');
$st->execute([$id]);
$order = $st->fetch();
if (!$order) {
    flash('error', 'That order does not exist.');
    redirect('admin/orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $newStatus = val('order_status');
    $newPay = val('payment_status');
    if (in_array($newStatus, order_statuses(), true) && in_array($newPay, payment_statuses(), true)) {
        $pdo = db();
        $pdo->beginTransaction();
        if ($newStatus === 'cancelled' && $order['order_status'] !== 'cancelled') {
            restore_stock((int) $order['id']);
        }
        $paidAt = $newPay === 'paid' ? ($order['paid_at'] ?: date('Y-m-d H:i:s')) : null;
        $up = $pdo->prepare('UPDATE vb_orders SET order_status = ?, payment_status = ?, paid_at = ? WHERE id = ?');
        $up->execute([$newStatus, $newPay, $paidAt, (int) $order['id']]);
        $pdo->commit();
        flash('success', 'Order updated.');
    } else {
        flash('error', 'Choose valid statuses.');
    }
    redirect('admin/order.php?id=' . (int) $order['id']);
}

$it = db()->prepare('SELECT * FROM vb_order_items WHERE order_id = ? ORDER BY id');
$it->execute([(int) $order['id']]);
$items = $it->fetchAll();

$pageTitle = 'Order ' . $order['order_ref'];
require __DIR__ . '/_header.php';
?>
<div class="page-head row-between">
    <div>
        <h1>Order <?= e($order['order_ref']) ?></h1>
        <p class="muted"><?= e(date('j F Y, g:i a', strtotime($order['created_at']))) ?></p>
    </div>
    <a class="btn" href="<?= e(url('admin/orders.php')) ?>">All orders</a>
</div>

<div class="admin-cols">
    <section>
        <h2>Items</h2>
        <table class="table">
            <thead><tr><th>Product</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Line total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr>
                    <td><?= e($i['product_name']) ?></td>
                    <td class="num"><?= e(money($i['unit_price'])) ?></td>
                    <td class="num"><?= (int) $i['quantity'] ?></td>
                    <td class="num"><?= e(money((float) $i['unit_price'] * (int) $i['quantity'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="3">Subtotal</td><td class="num"><?= e(money($order['subtotal'])) ?></td></tr>
                <tr><td colspan="3">Shipping</td><td class="num"><?= e(money($order['shipping'])) ?></td></tr>
                <tr class="strong"><td colspan="3">Total</td><td class="num"><?= e(money($order['total'])) ?></td></tr>
            </tfoot>
        </table>

        <h2>Customer</h2>
        <address class="address">
            <?= e($order['full_name']) ?><br>
            <?= e($order['address']) ?><br>
            <?= e($order['city']) ?>, <?= e($order['region']) ?><br>
            <?= e($order['phone']) ?> · <a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a>
        </address>
        <?php if (!empty($order['notes'])): ?><p class="muted">Notes: <?= e($order['notes']) ?></p><?php endif; ?>
    </section>

    <section>
        <h2>Update order</h2>
        <form method="post" action="<?= e(url('admin/order.php?id=' . (int) $order['id'])) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="order_status">Order status</label>
                <select id="order_status" name="order_status">
                    <?php foreach (order_statuses() as $s): ?>
                        <option value="<?= e($s) ?>"<?= $order['order_status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="payment_status">Payment status</label>
                <select id="payment_status" name="payment_status">
                    <?php foreach (payment_statuses() as $s): ?>
                        <option value="<?= e($s) ?>"<?= $order['payment_status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="muted small">Payment method: <?= $order['payment_method'] === 'cod' ? 'Pay on delivery. Mark it paid once the courier hands over the cash.' : 'Card or mobile money' ?><?= !empty($order['paystack_reference']) ? '. Reference ' . e($order['paystack_reference']) : '' ?></p>
            <button class="btn btn-primary" type="submit">Save changes</button>
            <p class="muted small">Cancelling an order puts its items back in stock.</p>
        </form>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
