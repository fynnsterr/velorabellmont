<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$pageTitle = 'Overview';
expire_unpaid_orders(20);
require __DIR__ . '/_header.php';

$today = date('Y-m-d');
$sToday = sales_summary($today, $today);
$s7 = sales_summary(date('Y-m-d', strtotime('-6 days')), $today);
$p7 = sales_summary(date('Y-m-d', strtotime('-13 days')), date('Y-m-d', strtotime('-7 days')));
$sMonth = sales_summary(date('Y-m-01'), $today);
$series = sales_series('day');
$att = admin_attention();
$low = low_stock_products(8);
$recent = db()->query('SELECT * FROM vb_orders ORDER BY id DESC LIMIT 8')->fetchAll();
$change = pct_change($s7['net'], $p7['net']);
$notes = [];
if (!cfg('mail.enabled', true)) {
    $notes[] = 'Email sending is switched off in config.php, so customers are not receiving order emails.';
}
?>
<div class="page-head row-between">
    <h1>Overview</h1>
    <p><a class="btn btn-sm" href="<?= e(url('admin/analytics.php')) ?>">Full analytics</a> <a class="btn btn-sm" href="<?= e(url('admin/reports.php')) ?>">Reports</a></p>
</div>

<?php foreach ($notes as $n): ?><p class="flash flash-info"><?= e($n) ?></p><?php endforeach; ?>

<div class="stats">
    <div class="stat"><p class="stat-num"><?= e(money($sToday['net'])) ?></p><p class="stat-label">Sales today (<?= (int) $sToday['orders'] ?> orders)</p></div>
    <div class="stat"><p class="stat-num"><?= e(money($s7['net'])) ?></p><p class="stat-label">Last 7 days<?= $change !== null ? ' (' . ($change >= 0 ? '+' : '') . e((string) $change) . '% on the week before)' : '' ?></p></div>
    <div class="stat"><p class="stat-num"><?= e(money($sMonth['net'])) ?></p><p class="stat-label">This month (<?= (int) $sMonth['orders'] ?> orders)</p></div>
    <div class="stat"><p class="stat-num"><?= e(money($sMonth['aov'])) ?></p><p class="stat-label">Average order this month</p></div>
</div>

<h2>Needs your attention</h2>
<ul class="attention">
    <li><a href="<?= e(url('admin/orders.php?status=pending')) ?>"><strong><?= (int) $att['to_process'] ?></strong> to start processing</a></li>
    <li><a href="<?= e(url('admin/orders.php?status=processing')) ?>"><strong><?= (int) $att['to_dispatch'] ?></strong> ready to dispatch</a></li>
    <li><a href="<?= e(url('admin/orders.php?status=dispatched')) ?>"><strong><?= (int) $att['on_the_road'] ?></strong> on the road</a></li>
    <li><a href="<?= e(url('admin/orders.php?status=pending&pay=pending')) ?>"><strong><?= (int) $att['awaiting_payment'] ?></strong> waiting for payment</a></li>
    <li><a href="<?= e(url('admin/refunds.php?status=pending')) ?>"><strong><?= (int) $att['refunds_pending'] ?></strong> refunds to send</a></li>
    <li><a href="<?= e(url('admin/enquiries.php?status=new')) ?>"><strong><?= (int) enquiry_new_count() ?></strong> new enquiries</a></li>
    <li><a href="<?= e(url('admin/inventory.php?view=low')) ?>"><strong><?= (int) $att['low_stock'] ?></strong> low or sold out</a></li>
</ul>

<h2>Sales, last 30 days</h2>
<div class="chart-box"><?= svg_bars(array_column($series['buckets'], 'label'), array_column($series['buckets'], 'net'), 'Net sales per day for the last 30 days', 'GH₵') ?></div>

<div class="admin-cols">
    <section>
        <h2>Recent orders</h2>
        <?php if ($recent): ?>
            <table class="table">
                <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $o): ?>
                    <tr>
                        <td><a href="<?= e(url('admin/order.php?id=' . (int) $o['id'])) ?>"><?= e($o['order_ref']) ?></a></td>
                        <td><?= e($o['full_name']) ?></td>
                        <td><?= badge($o['order_status']) ?></td>
                        <td><?= pay_badge($o['payment_status']) ?></td>
                        <td class="num"><?= e(money($o['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">No orders yet. They will show up here as customers check out.</p>
        <?php endif; ?>
    </section>
    <section>
        <h2>Low stock</h2>
        <?php if ($low): ?>
            <ul class="plain-list">
                <?php foreach ($low as $p): ?>
                    <li><a href="<?= e(url('admin/stock.php?id=' . (int) $p['id'])) ?>"><?= e($p['name']) ?></a> <span class="muted"><?= (int) $p['stock'] === 0 ? 'sold out' : (int) $p['stock'] . ' left' ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">Every product is above its alert level.</p>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
