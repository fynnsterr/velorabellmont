<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$pageTitle = 'Overview';
require __DIR__ . '/_header.php';

$orderCount = (int) db()->query('SELECT COUNT(*) FROM vb_orders')->fetchColumn();
$revenue = (float) db()->query("SELECT COALESCE(SUM(total), 0) FROM vb_orders WHERE payment_status = 'paid' AND order_status <> 'cancelled'")->fetchColumn();
$toShip = (int) db()->query("SELECT COUNT(*) FROM vb_orders WHERE order_status IN ('pending', 'processing')")->fetchColumn();
$productCount = (int) db()->query('SELECT COUNT(*) FROM vb_products WHERE active = 1')->fetchColumn();
$low = db()->query('SELECT id, name, stock FROM vb_products WHERE active = 1 AND stock <= 5 ORDER BY stock, name LIMIT 10')->fetchAll();
$recent = db()->query('SELECT * FROM vb_orders ORDER BY id DESC LIMIT 8')->fetchAll();
?>
<div class="page-head"><h1>Overview</h1></div>

<div class="stats">
    <div class="stat"><p class="stat-num"><?= $orderCount ?></p><p class="stat-label">Orders</p></div>
    <div class="stat"><p class="stat-num"><?= e(money($revenue)) ?></p><p class="stat-label">Paid revenue</p></div>
    <div class="stat"><p class="stat-num"><?= $toShip ?></p><p class="stat-label">To pack and ship</p></div>
    <div class="stat"><p class="stat-num"><?= $productCount ?></p><p class="stat-label">Active products</p></div>
</div>

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
                        <td><?= badge($o['payment_status']) ?></td>
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
                    <li><a href="<?= e(url('admin/product_edit.php?id=' . (int) $p['id'])) ?>"><?= e($p['name']) ?></a> <span class="muted"><?= (int) $p['stock'] === 0 ? 'sold out' : (int) $p['stock'] . ' left' ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">Every product has more than 5 in stock.</p>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
