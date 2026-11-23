<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$email = strtolower(trim(val('email')));
$c = $email !== '' ? customer_detail($email) : null;
if (!$c) {
    flash('error', 'We could not find that customer.');
    redirect('admin/customers.php');
}
$pageTitle = $c['name'];
require __DIR__ . '/_header.php';
$wa = wa_url('Hello ' . $c['name'] . ', this is ' . cfg('site.name') . '.');
?>
<div class="page-head row-between">
    <div><h1><?= e($c['name']) ?></h1><p class="muted"><?= $c['user'] ? 'Registered account' : 'Guest customer' ?> · <?= $c['subscribed'] ? 'Subscribed to emails' : 'Not subscribed to emails' ?></p></div>
    <a class="btn" href="<?= e(url('admin/customers.php')) ?>">All customers</a>
</div>

<div class="stats">
    <div class="stat"><p class="stat-num"><?= (int) $c['order_count'] ?></p><p class="stat-label">Orders</p></div>
    <div class="stat"><p class="stat-num"><?= e(money($c['spend'])) ?></p><p class="stat-label">Total spent</p></div>
    <div class="stat"><p class="stat-num"><?= (int) $c['points'] ?></p><p class="stat-label">Reward points</p></div>
    <div class="stat"><p class="stat-num"><?= (int) $c['wishlist'] ?></p><p class="stat-label">Wishlist items</p></div>
</div>

<div class="admin-cols">
    <section>
        <h2>Purchase history</h2>
        <?php if ($c['orders']): ?>
            <table class="table">
                <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead>
                <tbody>
                <?php foreach ($c['orders'] as $o): ?>
                    <tr>
                        <td><a href="<?= e(url('admin/order.php?id=' . (int) $o['id'])) ?>"><?= e($o['order_ref']) ?></a><?= $o['kind'] === 'giftcard' ? ' <span class="muted small">gift card</span>' : '' ?></td>
                        <td><?= e(date('j M Y', strtotime((string) $o['created_at']))) ?></td>
                        <td><?= badge($o['order_status']) ?></td>
                        <td><?= pay_badge($o['payment_status']) ?></td>
                        <td class="num"><?= e(money($o['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">No orders yet.</p>
        <?php endif; ?>
    </section>
    <section>
        <h2>Contact</h2>
        <p><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php if (!empty($c['phone'])): ?><br><?= e($c['phone']) ?><?php endif; ?></p>
        <p><a class="btn btn-sm" href="<?= e($wa) ?>" target="_blank" rel="noopener">Message on WhatsApp</a></p>
        <?php if ($c['user']): ?>
            <p class="muted small">Joined <?= e(date('j M Y', strtotime((string) $c['user']['created_at']))) ?> · <?= (int) $c['reviews'] ?> reviews</p>
        <?php endif; ?>
        <h2>Addresses</h2>
        <?php if ($c['addresses']): ?>
            <?php foreach ($c['addresses'] as $a): ?>
                <address class="address small"><strong><?= e($a['label']) ?></strong><?= (int) $a['is_default'] === 1 ? ' (default)' : '' ?><br><?= e($a['address']) ?>, <?= e($a['city']) ?>, <?= e($a['region']) ?></address>
            <?php endforeach; ?>
        <?php elseif ($c['orders']): ?>
            <address class="address small">From the latest order:<br><?= e($c['orders'][0]['address']) ?>, <?= e($c['orders'][0]['city']) ?>, <?= e($c['orders'][0]['region']) ?></address>
        <?php else: ?>
            <p class="muted small">None saved.</p>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
