<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();
$adminId = (int) $admin['id'];

$id = (int) val('id');
$order = order_by_id($id);
if (!$order) {
    flash('error', 'That order does not exist.');
    redirect('admin/orders.php');
}
$self = 'admin/order.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = val('action');
    if (!staff_can_action($admin['role'], $action)) {
        flash('error', 'Your role cannot do that. Ask an admin.');
        redirect('admin/order.php?id=' . $id);
    }
    $result = [false, 'Choose an action.'];
    if ($action === 'status') {
        $result = order_set_status($id, val('new_status'), [
            'courier' => val('courier'),
            'tracking_no' => val('tracking_no'),
            'tracking_url' => val('tracking_url'),
            'note' => val('note'),
            'notify' => val('notify') === '1',
        ], $adminId);
    } elseif ($action === 'pay') {
        $result = order_confirm_payment($id, val('reference'), $adminId);
    } elseif ($action === 'shipping') {
        $result = order_update_shipping($id, val('courier'), val('tracking_no'), val('tracking_url'), $adminId);
    } elseif ($action === 'refund_create') {
        $result = refund_create($id, (float) str_replace(',', '', val('amount')), val('reason'), val('method'), trim(val('note')), val('restock') === '1', $adminId);
    } elseif ($action === 'refund_sent' || $action === 'refund_reject') {
        $own = db()->prepare('SELECT COUNT(*) FROM vb_refunds WHERE id = ? AND order_id = ?');
        $own->execute([(int) val('refund_id'), $id]);
        $result = (int) $own->fetchColumn() > 0
            ? refund_set_status((int) val('refund_id'), $action === 'refund_sent' ? 'paid' : 'rejected', trim(val('reference')), $adminId)
            : [false, 'That refund does not belong to this order.'];
    }
    flash($result[0] ? 'success' : 'error', $result[1]);
    redirect($self);
}

$items = order_items_for($id);
$events = order_events($id);
$refunds = refunds_for_order($id);
$next = order_transitions($order);
$isCod = $order['payment_method'] === 'cod';
$paid = order_is_paid($order);
$blocked = $order['kind'] === 'shop' && !$isCod && !$paid;
if ($blocked) {
    $next = array_values(array_intersect($next, ['cancelled']));
}
$left = order_refundable($order);
$couriers = (array) cfg('shipping.couriers', ['DHL', 'Ghana Post', 'Speedaf', 'Yango Delivery', 'Our own rider']);

$pageTitle = 'Order ' . $order['order_ref'];
require __DIR__ . '/_header.php';
?>
<div class="page-head row-between">
    <div>
        <h1>Order <?= e($order['order_ref']) ?></h1>
        <p class="muted"><?= e(date('j F Y, g:i a', strtotime($order['created_at']))) ?> · <?= badge($order['order_status']) ?> <?= pay_badge($order['payment_status']) ?></p>
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
                    <td><?= e($i['product_name']) ?><?= !empty($i['size']) ? ' <span class="muted">(size ' . e($i['size']) . ')</span>' : '' ?></td>
                    <td class="num"><?= e(money($i['unit_price'])) ?></td>
                    <td class="num"><?= (int) $i['quantity'] ?></td>
                    <td class="num"><?= e(money((float) $i['unit_price'] * (int) $i['quantity'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="3">Subtotal</td><td class="num"><?= e(money($order['subtotal'])) ?></td></tr>
                <?php if ((float) ($order['discount'] ?? 0) > 0): ?>
                    <tr><td colspan="3">Discount<?= !empty($order['promo_code']) ? ' (' . e($order['promo_code']) . ')' : '' ?></td><td class="num">-<?= e(money($order['discount'])) ?></td></tr>
                <?php endif; ?>
                <?php if ((int) $order['points_used'] > 0): ?>
                    <tr><td colspan="3">Points used (<?= (int) $order['points_used'] ?>)</td><td class="num">-<?= e(money($order['points_discount'])) ?></td></tr>
                <?php endif; ?>
                <?php if ((float) $order['gift_amount'] > 0): ?>
                    <tr><td colspan="3">Gift card <?= e((string) $order['gift_code']) ?></td><td class="num">-<?= e(money($order['gift_amount'])) ?></td></tr>
                <?php endif; ?>
                <tr><td colspan="3">Delivery</td><td class="num"><?= e(money($order['shipping'])) ?></td></tr>
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
        <p class="muted small"><?= $order['kind'] === 'giftcard' ? 'Gift card purchase. ' : '' ?><?= $order['user_id'] === null ? 'Guest checkout, no account.' : 'Registered customer.' ?><?= !empty($order['delivery_estimate']) ? ' Promised delivery: ' . e($order['delivery_estimate']) . '.' : '' ?>
            <a href="<?= e(url('admin/customer.php?email=' . urlencode($order['email']))) ?>">Customer history</a> · <a href="<?= e(wa_url('Hello ' . $order['full_name'] . ', about your ' . cfg('site.name') . ' order ' . $order['order_ref'])) ?>" target="_blank" rel="noopener">Message on WhatsApp</a></p>
        <?php if (!empty($order['notes'])): ?><p class="muted">Notes: <?= e($order['notes']) ?></p><?php endif; ?>
        <?php if ($order['kind'] === 'shop' && staff_can($admin['role'], 'packing.php')): ?><p><a class="text-link" href="<?= e(url('admin/packing.php?id=' . $id)) ?>" target="_blank" rel="noopener">Print packing slip</a> · <a class="text-link" href="<?= e(url('admin/packing.php?id=' . $id . '&mode=label')) ?>" target="_blank" rel="noopener">Print shipping label</a></p><?php endif; ?>

        <h2>History</h2>
        <ul class="history">
            <?php foreach (array_reverse($events) as $ev): ?>
                <li><strong><?= e(order_event_label($ev['event'])) ?></strong><?= !empty($ev['note']) ? ' <span class="muted">' . e($ev['note']) . '</span>' : '' ?> <span class="muted small"><?= e(date('j M Y, g:i a', strtotime((string) $ev['created_at']))) ?></span></li>
            <?php endforeach; ?>
            <?php if (!$events): ?><li class="muted">No events recorded for this order.</li><?php endif; ?>
        </ul>
    </section>

    <section class="side-forms">
        <h2>Fulfilment</h2>
        <?php if ($order['kind'] === 'shop' && $order['order_status'] !== 'cancelled'): ?>
            <ol class="mini-flow">
                <?php foreach (order_timeline($order, $events) as $step): ?>
                    <li class="mf-<?= e($step['state']) ?>"><?= e($step['label']) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($blocked): ?>
            <form method="post" action="<?= e(url($self)) ?>" class="form card-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="pay">
                <h3>Confirm payment</h3>
                <p class="muted small">Waiting for <?= e(pay_label($order)) ?>. Paystack payments confirm themselves. Use this if you have verified the money another way.</p>
                <div class="field"><label for="reference">Reference (optional)</label><input id="reference" type="text" name="reference" maxlength="80"></div>
                <button class="btn btn-primary" type="submit">Mark as paid</button>
            </form>
        <?php elseif ($isCod && !$paid && $order['order_status'] !== 'cancelled'): ?>
            <p class="muted small">Pay on delivery. It is marked paid when you mark it delivered, or sooner here.</p>
            <form method="post" action="<?= e(url($self)) ?>" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="reference" value="">
                <button class="btn btn-sm" type="submit">Cash received</button>
            </form>
        <?php endif; ?>

        <?php if ($next): ?>
            <form method="post" action="<?= e(url($self)) ?>" class="form card-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <h3>Move this order on</h3>
                <div class="field">
                    <label for="new_status">New status</label>
                    <select id="new_status" name="new_status">
                        <?php foreach ($next as $s): ?><option value="<?= e($s) ?>"><?= e(order_status_label($s)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php if ($order['kind'] === 'shop' && !$blocked): ?>
                    <div class="field">
                        <label for="courier">Courier (needed to dispatch)</label>
                        <input id="courier" type="text" name="courier" list="couriers" maxlength="60" value="<?= e((string) $order['courier']) ?>">
                        <datalist id="couriers"><?php foreach ($couriers as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                    </div>
                    <div class="field"><label for="tracking_no">Tracking number</label><input id="tracking_no" type="text" name="tracking_no" maxlength="80" value="<?= e((string) $order['tracking_no']) ?>"></div>
                    <div class="field"><label for="tracking_url">Tracking link (optional)</label><input id="tracking_url" type="url" name="tracking_url" maxlength="255" value="<?= e((string) $order['tracking_url']) ?>" placeholder="https://"></div>
                <?php endif; ?>
                <div class="field"><label for="note">Note (optional)</label><input id="note" type="text" name="note" maxlength="255"></div>
                <label class="check"><input type="checkbox" name="notify" value="1" checked> Email the customer</label>
                <button class="btn btn-primary" type="submit">Update status</button>
                <p class="muted small">Cancelling puts the items back in stock and returns points and gift card amounts. A paid order then needs a refund below.</p>
            </form>
        <?php elseif ($order['order_status'] === 'delivered'): ?>
            <p class="muted">Delivered<?= !empty($order['delivered_at']) ? ' on ' . e(date('j M Y', strtotime((string) $order['delivered_at']))) : '' ?>. Returns go through a refund.</p>
        <?php elseif ($order['order_status'] === 'cancelled'): ?>
            <p class="muted">This order was cancelled.</p>
        <?php endif; ?>

        <?php if (in_array($order['order_status'], ['dispatched', 'out_for_delivery'], true)): ?>
            <form method="post" action="<?= e(url($self)) ?>" class="form card-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="shipping">
                <h3>Shipping details</h3>
                <div class="field"><label for="s_courier">Courier</label><input id="s_courier" type="text" name="courier" list="couriers" maxlength="60" value="<?= e((string) $order['courier']) ?>"></div>
                <div class="field"><label for="s_tracking">Tracking number</label><input id="s_tracking" type="text" name="tracking_no" maxlength="80" value="<?= e((string) $order['tracking_no']) ?>"></div>
                <div class="field"><label for="s_url">Tracking link</label><input id="s_url" type="url" name="tracking_url" maxlength="255" value="<?= e((string) $order['tracking_url']) ?>"></div>
                <button class="btn btn-sm" type="submit">Save shipping details</button>
            </form>
        <?php elseif (!empty($order['courier'])): ?>
            <p class="muted small">Courier: <?= e($order['courier']) ?><?= !empty($order['tracking_no']) ? ' · ' . e($order['tracking_no']) : '' ?></p>
        <?php endif; ?>

        <h2>Refunds</h2>
        <?php foreach ($refunds as $r): ?>
            <div class="refund-row">
                <p><strong><?= e(money($r['amount'])) ?></strong> <?= refund_badge($r['status']) ?> <span class="muted small"><?= e(refund_reasons()[$r['reason']] ?? $r['reason']) ?> · <?= e(refund_methods()[$r['method']] ?? $r['method']) ?><?= (int) $r['restock'] === 1 ? ' · items back in stock' : '' ?></span></p>
                <?php if (!empty($r['note'])): ?><p class="muted small"><?= e($r['note']) ?></p><?php endif; ?>
                <?php if ($r['status'] === 'pending'): ?>
                    <form method="post" action="<?= e(url($self)) ?>" class="inline-form refund-actions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="refund_id" value="<?= (int) $r['id'] ?>">
                        <label class="sr-only" for="ref_<?= (int) $r['id'] ?>">Reference</label>
                        <input id="ref_<?= (int) $r['id'] ?>" type="text" name="reference" maxlength="80" placeholder="Reference (optional)">
                        <button class="btn btn-sm btn-primary" type="submit" name="action" value="refund_sent">Mark as sent</button>
                        <button class="btn btn-sm" type="submit" name="action" value="refund_reject">Reject</button>
                    </form>
                <?php elseif (!empty($r['reference'])): ?>
                    <p class="muted small">Reference <?= e($r['reference']) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($left > 0): ?>
            <form method="post" action="<?= e(url($self)) ?>" class="form card-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="refund_create">
                <h3>Record a refund</h3>
                <p class="muted small">Up to <?= e(money($left)) ?> can be refunded. This records it. You still send the money from Paystack, your mobile money wallet or the bank, then mark it as sent.</p>
                <div class="field"><label for="amount">Amount (GH₵)</label><input id="amount" type="text" inputmode="decimal" name="amount" value="<?= e(number_format($left, 2, '.', '')) ?>"></div>
                <div class="field"><label for="reason">Reason</label><select id="reason" name="reason"><?php foreach (refund_reasons() as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="method">Send the money by</label><select id="method" name="method"><?php foreach (refund_methods() as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="r_note">Note (optional)</label><input id="r_note" type="text" name="note" maxlength="255"></div>
                <?php if (!order_restocked($id)): ?><label class="check"><input type="checkbox" name="restock" value="1"> Put the items back in stock (full refunds only)</label><?php endif; ?>
                <button class="btn" type="submit">Record refund</button>
            </form>
        <?php elseif (!$refunds): ?>
            <p class="muted small"><?= $paid ? 'Nothing to refund.' : 'Refunds can be recorded once an order has been paid.' ?></p>
        <?php endif; ?>
        <p class="muted small">Payment: <?= e(pay_label($order)) ?><?= !empty($order['paystack_reference']) ? '. Reference ' . e($order['paystack_reference']) : '' ?></p>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
