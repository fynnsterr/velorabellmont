<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$pageTitle = 'Customers';
require __DIR__ . '/_header.php';

$period = array_key_exists(val('period'), sales_period_defs()) ? val('period') : 'month';
$q = trim(val('q'));
$sum = customer_summary();
$series = customer_series($period);
$rows = customer_rows($q);
$shown = array_slice($rows, 0, 100);
$labels = array_column($series, 'label');
?>
<div class="page-head row-between">
    <div><h1>Customers</h1><p class="muted">Everyone who has ordered, with or without an account, plus registered accounts.</p></div>
    <a class="btn btn-sm" href="<?= e(url('admin/reports.php')) ?>">Export customers</a>
</div>

<div class="stats">
    <div class="stat"><p class="stat-num"><?= (int) $sum['customers'] ?></p><p class="stat-label">Customers who have paid</p></div>
    <div class="stat"><p class="stat-num"><?= e((string) $sum['repeat_rate']) ?>%</p><p class="stat-label">Come back for a second order (<?= (int) $sum['repeat'] ?>)</p></div>
    <div class="stat"><p class="stat-num"><?= e(money($sum['spend_each'])) ?></p><p class="stat-label">Average spend per customer</p></div>
    <div class="stat"><p class="stat-num"><?= (int) $sum['new_accounts'] ?></p><p class="stat-label">New accounts, last 30 days (<?= (int) $sum['accounts'] ?> in all)</p></div>
</div>

<div class="pill-row" role="navigation" aria-label="Period">
    <?php foreach (sales_period_defs() as $k => $label): ?>
        <a class="pill<?= $period === $k ? ' is-active' : '' ?>" href="<?= e(url('admin/customers.php?period=' . $k)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
<div class="admin-cols even">
    <section>
        <h2>New customers</h2>
        <div class="chart-box"><?= svg_bars($labels, array_map('floatval', array_column($series, 'new')), 'New customers: first paid order in the period') ?></div>
    </section>
    <section>
        <h2>Returning customers</h2>
        <div class="chart-box"><?= svg_bars($labels, array_map('floatval', array_column($series, 'returning')), 'Returning customers: ordered again in the period') ?></div>
    </section>
</div>
<p class="muted small">New means their first paid order fell in the period. Returning means they paid for an order in the period and had ordered before.</p>

<h2>Customer list</h2>
<form method="get" action="<?= e(url('admin/customers.php')) ?>" class="filter-form">
    <input type="hidden" name="period" value="<?= e($period) ?>">
    <label class="sr-only" for="q">Search</label>
    <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email or phone">
    <button class="btn btn-sm" type="submit">Search</button>
</form>
<?php if ($shown): ?>
    <table class="table">
        <thead><tr><th>Customer</th><th>Contact</th><th class="num">Orders</th><th class="num">Spent</th><th>Last order</th></tr></thead>
        <tbody>
        <?php foreach ($shown as $c): ?>
            <tr>
                <td><a href="<?= e(url('admin/customer.php?email=' . urlencode($c['email']))) ?>"><?= e($c['name']) ?></a> <?= $c['user_id'] ? '' : '<span class="muted small">guest</span>' ?></td>
                <td><?= e($c['email']) ?><?= !empty($c['phone']) ? '<br><span class="muted small">' . e($c['phone']) . '</span>' : '' ?></td>
                <td class="num"><?= (int) $c['orders'] ?></td>
                <td class="num"><?= e(money($c['spend'])) ?></td>
                <td><?= !empty($c['last_order']) ? e(date('j M Y', strtotime((string) $c['last_order']))) : '<span class="muted">none</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (count($rows) > count($shown)): ?><p class="muted small">Showing the first <?= count($shown) ?> of <?= count($rows) ?>. Export the list to see everyone.</p><?php endif; ?>
<?php else: ?>
    <p class="muted">No customers match.</p>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
