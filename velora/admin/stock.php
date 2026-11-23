<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();
$id = (int) val('id');
$st = db()->prepare('SELECT p.*, c.name AS category_name FROM vb_products p JOIN vb_categories c ON c.id = p.category_id WHERE p.id = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    flash('error', 'That product does not exist.');
    redirect('admin/inventory.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (val('action') === 'adjust') {
        $qty = trim(val('qty'));
        if (!ctype_digit($qty) || (int) $qty < 1 || (int) $qty > 100000) {
            flash('error', 'Enter a whole number of pieces, 1 or more.');
        } else {
            $dir = val('direction') === 'remove' ? -1 : 1;
            $reason = val('reason');
            if ($dir > 0 && !in_array($reason, ['restock', 'return', 'adjust'], true)) {
                $reason = 'restock';
            }
            if ($dir < 0 && !in_array($reason, ['damaged', 'adjust'], true)) {
                $reason = 'damaged';
            }
            $r = stock_adjust($id, $dir * (int) $qty, $reason, trim(val('note')), (int) $admin['id']);
            flash($r[0] ? 'success' : 'error', $r[1]);
        }
    } elseif (val('action') === 'level') {
        $lv = trim(val('low_stock_at'));
        if ($lv !== '' && (!ctype_digit($lv) || (int) $lv > 100000)) {
            flash('error', 'Enter a whole number or leave empty for the shop default.');
        } else {
            db()->prepare('UPDATE vb_products SET low_stock_at = ? WHERE id = ?')->execute([$lv !== '' ? (int) $lv : null, $id]);
            stock_alert_check($id);
            flash('success', 'Alert level saved.');
        }
    }
    redirect('admin/stock.php?id=' . $id);
}

$moves = stock_moves_for($id, 60);
$level = low_stock_level($p);
$pageTitle = 'Stock: ' . $p['name'];
require __DIR__ . '/_header.php';
?>
<div class="page-head row-between">
    <div><h1><?= e($p['name']) ?></h1><p class="muted"><?= e($p['category_name']) ?> · <strong><?= (int) $p['stock'] ?></strong> in stock · alert at <?= (int) $level ?> <?= (int) $p['stock'] <= $level ? '<span class="badge badge-pending">Low</span>' : '' ?></p></div>
    <a class="btn" href="<?= e(url('admin/inventory.php')) ?>">Inventory</a>
</div>

<div class="admin-cols">
    <section>
        <h2>Stock movements</h2>
        <?php if ($moves): ?>
            <table class="table">
                <thead><tr><th>When</th><th class="num">Change</th><th class="num">Balance</th><th>Reason</th><th>Order or note</th></tr></thead>
                <tbody>
                <?php foreach ($moves as $m): ?>
                    <tr>
                        <td><?= e(date('j M Y, g:i a', strtotime((string) $m['created_at']))) ?></td>
                        <td class="num"><?= (int) $m['qty_change'] > 0 ? '+' : '' ?><?= (int) $m['qty_change'] ?></td>
                        <td class="num"><?= (int) $m['balance'] ?></td>
                        <td><?= e(stock_reasons()[$m['reason']] ?? $m['reason']) ?></td>
                        <td><?= !empty($m['order_ref']) ? '<a href="' . e(url('admin/order.php?id=' . (int) $m['order_id'])) . '">' . e($m['order_ref']) . '</a> ' : '' ?><?= e((string) $m['note']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">No movements recorded for this piece yet.</p>
        <?php endif; ?>
    </section>
    <section class="side-forms">
        <h2>Adjust stock</h2>
        <form method="post" action="<?= e(url('admin/stock.php?id=' . $id)) ?>" class="form card-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="adjust">
            <div class="field"><label for="direction">What happened</label>
                <select id="direction" name="direction"><option value="add">Stock added or returned</option><option value="remove">Stock removed (damaged, lost)</option></select></div>
            <div class="field"><label for="reason">Reason</label>
                <select id="reason" name="reason"><option value="restock">Restock</option><option value="return">Customer return</option><option value="damaged">Damaged or lost</option><option value="adjust">Correction</option></select></div>
            <div class="field"><label for="qty">Pieces</label><input id="qty" type="text" inputmode="numeric" name="qty" required></div>
            <div class="field"><label for="note">Note (optional)</label><input id="note" type="text" name="note" maxlength="160"></div>
            <button class="btn btn-primary" type="submit">Save adjustment</button>
        </form>
        <form method="post" action="<?= e(url('admin/stock.php?id=' . $id)) ?>" class="form card-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="level">
            <h3>Alert level</h3>
            <div class="field"><label for="low_stock_at">Email me when stock falls to</label><input id="low_stock_at" type="text" inputmode="numeric" name="low_stock_at" value="<?= e((string) ($p['low_stock_at'] ?? '')) ?>" placeholder="<?= (int) cfg('stock.low_at', 5) ?>"></div>
            <button class="btn btn-sm" type="submit">Save</button>
        </form>
        <p><a href="<?= e(url('admin/product_edit.php?id=' . $id)) ?>">Edit product details</a></p>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
