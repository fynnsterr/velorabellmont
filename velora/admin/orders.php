<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$pageTitle = 'Orders';
require __DIR__ . '/_header.php';

$filter = val('status');
$sql = 'SELECT * FROM vb_orders';
$params = [];
if (in_array($filter, order_statuses(), true)) {
    $sql .= ' WHERE order_status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY id DESC LIMIT 200';
$st = db()->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();
?>
<div class="page-head"><h1>Orders</h1></div>

<div class="pill-row" role="navigation" aria-label="Filter by status">
    <a class="pill<?= $filter === '' ? ' is-active' : '' ?>" href="<?= e(url('admin/orders.php')) ?>">All</a>
    <?php foreach (order_statuses() as $s): ?>
        <a class="pill<?= $filter === $s ? ' is-active' : '' ?>" href="<?= e(url('admin/orders.php?status=' . $s)) ?>"><?= e(ucfirst($s)) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($orders): ?>
<table class="table">
    <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Region</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td><a href="<?= e(url('admin/order.php?id=' . (int) $o['id'])) ?>"><?= e($o['order_ref']) ?></a></td>
            <td><?= e(date('j M Y', strtotime($o['created_at']))) ?></td>
            <td><?= e($o['full_name']) ?></td>
            <td><?= e($o['region']) ?></td>
            <td><?= badge($o['order_status']) ?></td>
            <td><?= badge($o['payment_status']) ?></td>
            <td class="num"><?= e(money($o['total'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
    <div class="empty"><h2>No orders here</h2><p>Orders with this status will appear here.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
