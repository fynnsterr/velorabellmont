<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$pageTitle = 'Inventory';
require __DIR__ . '/_header.php';

$view = in_array(val('view'), ['low', 'out', 'moves'], true) ? val('view') : 'all';
$q = trim(val('q'));
$all = db()->query('SELECT p.*, c.name AS category_name FROM vb_products p JOIN vb_categories c ON c.id = p.category_id ORDER BY p.name')->fetchAll();
$units = 0;
$atCost = 0.0;
$atPrice = 0.0;
$low = 0;
$out = 0;
$rows = [];
foreach ($all as $p) {
    $stock = (int) $p['stock'];
    $level = low_stock_level($p);
    $state = $stock === 0 ? 'out' : ($stock <= $level ? 'low' : 'ok');
    if ((int) $p['active'] === 1) {
        $units += $stock;
        $atCost += $p['cost_price'] !== null ? (float) $p['cost_price'] * $stock : 0;
        $atPrice += (float) $p['price'] * $stock;
        $low += $state === 'low' ? 1 : 0;
        $out += $state === 'out' ? 1 : 0;
    }
    $p['state'] = $state;
    $p['level'] = $level;
    if ($view === 'low' && $state === 'ok') {
        continue;
    }
    if ($view === 'out' && $state !== 'out') {
        continue;
    }
    if ($q !== '' && stripos($p['name'], $q) === false) {
        continue;
    }
    $rows[] = $p;
}
$moves = [];
if ($view === 'moves') {
    $moves = db()->query('SELECT m.*, p.name AS product_name, o.order_ref FROM vb_stock_moves m JOIN vb_products p ON p.id = m.product_id LEFT JOIN vb_orders o ON o.id = m.order_id ORDER BY m.id DESC LIMIT 100')->fetchAll();
}
$tabs = ['all' => 'All products', 'low' => 'Low stock', 'out' => 'Sold out', 'moves' => 'Stock movements'];
?>
<div class="page-head row-between">
    <div><h1>Inventory</h1><p class="muted">Stock levels, movements and alerts. Active products only in the totals.</p></div>
    <a class="btn btn-sm" href="<?= e(url('admin/reports.php')) ?>">Export inventory</a>
</div>

<div class="stats">
    <div class="stat"><p class="stat-num"><?= (int) $units ?></p><p class="stat-label">Units in stock</p></div>
    <div class="stat"><p class="stat-num"><?= e(money($atCost)) ?></p><p class="stat-label">Stock value at cost</p></div>
    <div class="stat"><p class="stat-num"><?= e(money($atPrice)) ?></p><p class="stat-label">Stock value at price</p></div>
    <div class="stat"><p class="stat-num"><?= (int) $low ?> / <?= (int) $out ?></p><p class="stat-label">Low / sold out</p></div>
</div>

<div class="pill-row" role="navigation" aria-label="View">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="pill<?= $view === $k ? ' is-active' : '' ?>" href="<?= e(url('admin/inventory.php?view=' . $k)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($view === 'moves'): ?>
    <?php if ($moves): ?>
        <table class="table">
            <thead><tr><th>When</th><th>Product</th><th class="num">Change</th><th class="num">Balance</th><th>Reason</th><th>Order</th><th>Note</th></tr></thead>
            <tbody>
            <?php foreach ($moves as $m): ?>
                <tr>
                    <td><?= e(date('j M, g:i a', strtotime((string) $m['created_at']))) ?></td>
                    <td><a href="<?= e(url('admin/stock.php?id=' . (int) $m['product_id'])) ?>"><?= e($m['product_name']) ?></a></td>
                    <td class="num"><?= (int) $m['qty_change'] > 0 ? '+' : '' ?><?= (int) $m['qty_change'] ?></td>
                    <td class="num"><?= (int) $m['balance'] ?></td>
                    <td><?= e(stock_reasons()[$m['reason']] ?? $m['reason']) ?></td>
                    <td><?= !empty($m['order_ref']) ? '<a href="' . e(url('admin/order.php?id=' . (int) $m['order_id'])) . '">' . e($m['order_ref']) . '</a>' : '' ?></td>
                    <td><?= e((string) $m['note']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No stock movements yet. Sales, cancellations, returns and adjustments are recorded here.</p>
    <?php endif; ?>
<?php else: ?>
    <form method="get" action="<?= e(url('admin/inventory.php')) ?>" class="filter-form">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <label class="sr-only" for="q">Search</label>
        <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search products">
        <button class="btn btn-sm" type="submit">Search</button>
    </form>
    <?php if ($rows): ?>
        <table class="table">
            <thead><tr><th>Product</th><th>Category</th><th class="num">In stock</th><th class="num">Alert at</th><th>Status</th><th class="num">Value at cost</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $p): ?>
                <tr>
                    <td><?= e($p['name']) ?><?= (int) $p['active'] === 1 ? '' : ' <span class="muted small">hidden</span>' ?></td>
                    <td><?= e($p['category_name']) ?></td>
                    <td class="num"><?= (int) $p['stock'] ?></td>
                    <td class="num"><?= (int) $p['level'] ?></td>
                    <td><?= $p['state'] === 'out' ? '<span class="badge badge-cancelled">Sold out</span>' : ($p['state'] === 'low' ? '<span class="badge badge-pending">Low</span>' : '<span class="badge badge-delivered">OK</span>') ?></td>
                    <td class="num"><?= $p['cost_price'] !== null ? e(money((float) $p['cost_price'] * (int) $p['stock'])) : '<span class="muted">-</span>' ?></td>
                    <td><a class="btn btn-sm" href="<?= e(url('admin/stock.php?id=' . (int) $p['id'])) ?>">Adjust</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">Nothing here.</p>
    <?php endif; ?>
    <p class="muted small">Alerts: when stock falls to its alert level you get one email (sent to <?= e((string) (cfg('stock.alert_email', '') ?: cfg('site.support_email'))) ?>). Another is sent after you restock and it falls again.</p>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
