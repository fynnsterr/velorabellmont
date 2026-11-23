<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$pageTitle = 'Orders';
require __DIR__ . '/_header.php';

$filter = val('status');
$pay = val('pay');
$q = trim(val('q'));
$from = val('from');
$to = val('to');
$where = ["kind = 'shop'"];
$params = [];
if (in_array($filter, order_statuses(), true)) {
    $where[] = 'order_status = ?';
    $params[] = $filter;
}
if (in_array($pay, payment_statuses(), true)) {
    $where[] = 'payment_status = ?';
    $params[] = $pay;
}
if ($q !== '') {
    $where[] = '(order_ref LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'created_at < ?';
    $params[] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
}
$st = db()->prepare('SELECT * FROM vb_orders WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200');
$st->execute($params);
$orders = $st->fetchAll();
$keep = array_filter(['pay' => $pay, 'q' => $q, 'from' => $from, 'to' => $to], fn ($v) => $v !== '');
?>
<div class="page-head"><h1>Orders</h1></div>

<div class="pill-row" role="navigation" aria-label="Filter by status">
    <a class="pill<?= $filter === '' ? ' is-active' : '' ?>" href="<?= e(url('admin/orders.php' . ($keep ? '?' . http_build_query($keep) : ''))) ?>">All</a>
    <?php foreach (order_statuses() as $s): ?>
        <a class="pill<?= $filter === $s ? ' is-active' : '' ?>" href="<?= e(url('admin/orders.php?' . http_build_query($keep + ['status' => $s]))) ?>"><?= e(order_status_label($s)) ?></a>
    <?php endforeach; ?>
</div>

<?php if (staff_can($admin['role'], 'packing.php')): ?>
<p class="muted small">Print: <a href="<?= e(url('admin/packing.php?status=processing')) ?>" target="_blank" rel="noopener">packing slips for processing orders</a> · <a href="<?= e(url('admin/packing.php?status=processing&mode=label')) ?>" target="_blank" rel="noopener">shipping labels for processing orders</a></p>
<?php endif; ?>
<form method="get" action="<?= e(url('admin/orders.php')) ?>" class="filter-form">
    <?php if ($filter !== ''): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
    <label class="sr-only" for="q">Search</label>
    <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Order, name, email or phone">
    <label class="sr-only" for="pay">Payment</label>
    <select id="pay" name="pay">
        <option value="">Any payment</option>
        <?php foreach (payment_statuses() as $s): ?><option value="<?= e($s) ?>"<?= $pay === $s ? ' selected' : '' ?>><?= e(order_status_label($s)) ?></option><?php endforeach; ?>
    </select>
    <label class="sr-only" for="from">From</label>
    <input id="from" type="date" name="from" value="<?= e($from) ?>">
    <label class="sr-only" for="to">To</label>
    <input id="to" type="date" name="to" value="<?= e($to) ?>">
    <button class="btn btn-sm" type="submit">Filter</button>
</form>

<?php if ($orders): ?>
<table class="table">
    <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Region</th><th>Status</th><th>Payment</th><th>Shipping</th><th class="num">Total</th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td><a href="<?= e(url('admin/order.php?id=' . (int) $o['id'])) ?>"><?= e($o['order_ref']) ?></a></td>
            <td><?= e(date('j M Y', strtotime($o['created_at']))) ?></td>
            <td><?= e($o['full_name']) ?></td>
            <td><?= e($o['region']) ?></td>
            <td><?= badge($o['order_status']) ?></td>
            <td><?= pay_badge($o['payment_status']) ?></td>
            <td><?= !empty($o['courier']) ? e($o['courier']) . (!empty($o['tracking_no']) ? ' <span class="muted small">' . e($o['tracking_no']) . '</span>' : '') : '<span class="muted">-</span>' ?></td>
            <td class="num"><?= e(money($o['total'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
    <div class="empty"><h2>No orders here</h2><p>Nothing matches these filters.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
