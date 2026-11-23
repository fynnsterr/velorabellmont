<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = val('action');
    if ($action === 'refund_sent' || $action === 'refund_reject') {
        $r = refund_set_status((int) val('refund_id'), $action === 'refund_sent' ? 'paid' : 'rejected', trim(val('reference')), (int) $admin['id']);
        flash($r[0] ? 'success' : 'error', $r[1]);
    }
    redirect('admin/refunds.php' . (val('status') !== '' ? '?status=' . urlencode(val('status')) : ''));
}

$status = in_array(val('status'), ['pending', 'paid', 'rejected'], true) ? val('status') : '';
$sql = 'SELECT r.*, o.order_ref, o.full_name, o.total AS order_total FROM vb_refunds r JOIN vb_orders o ON o.id = r.order_id' . ($status !== '' ? ' WHERE r.status = ?' : '') . ' ORDER BY r.id DESC LIMIT 200';
$st = db()->prepare($sql);
$st->execute($status !== '' ? [$status] : []);
$rows = $st->fetchAll();
$pendingTotal = (float) db()->query("SELECT COALESCE(SUM(amount), 0) FROM vb_refunds WHERE status = 'pending'")->fetchColumn();
$paidTotal = (float) db()->query("SELECT COALESCE(SUM(amount), 0) FROM vb_refunds WHERE status = 'paid'")->fetchColumn();
$pageTitle = 'Refunds';
require __DIR__ . '/_header.php';
?>
<div class="page-head"><h1>Refunds</h1><p class="muted">Record refunds from the order page. Here you can follow them up and mark them as sent.</p></div>

<div class="stats">
    <div class="stat"><p class="stat-num"><?= e(money($pendingTotal)) ?></p><p class="stat-label">Waiting to be sent</p></div>
    <div class="stat"><p class="stat-num"><?= e(money($paidTotal)) ?></p><p class="stat-label">Sent in total</p></div>
</div>

<div class="pill-row" role="navigation" aria-label="Filter by status">
    <?php foreach (['' => 'All', 'pending' => 'To send', 'paid' => 'Sent', 'rejected' => 'Rejected'] as $k => $label): ?>
        <a class="pill<?= $status === $k ? ' is-active' : '' ?>" href="<?= e(url('admin/refunds.php' . ($k !== '' ? '?status=' . $k : ''))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($rows): ?>
    <table class="table">
        <thead><tr><th>Order</th><th>Customer</th><th>Recorded</th><th>Reason</th><th>Method</th><th>Status</th><th class="num">Amount</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><a href="<?= e(url('admin/order.php?id=' . (int) $r['order_id'])) ?>"><?= e($r['order_ref']) ?></a></td>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e(date('j M Y', strtotime((string) $r['created_at']))) ?></td>
                <td><?= e(refund_reasons()[$r['reason']] ?? $r['reason']) ?></td>
                <td><?= e(refund_methods()[$r['method']] ?? $r['method']) ?></td>
                <td><?= refund_badge($r['status']) ?></td>
                <td class="num"><?= e(money($r['amount'])) ?></td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                        <form method="post" action="<?= e(url('admin/refunds.php')) ?>" class="inline-form refund-actions">
                            <?= csrf_field() ?>
                            <input type="hidden" name="refund_id" value="<?= (int) $r['id'] ?>">
                            <input type="hidden" name="status" value="<?= e($status) ?>">
                            <label class="sr-only" for="rf_<?= (int) $r['id'] ?>">Reference</label>
                            <input id="rf_<?= (int) $r['id'] ?>" type="text" name="reference" maxlength="80" placeholder="Reference">
                            <button class="btn btn-sm btn-primary" type="submit" name="action" value="refund_sent">Sent</button>
                            <button class="btn btn-sm" type="submit" name="action" value="refund_reject">Reject</button>
                        </form>
                    <?php else: ?>
                        <span class="muted small"><?= e((string) $r['reference']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="empty"><h2>No refunds</h2><p>When you record a refund on an order it will appear here.</p></div>
<?php endif; ?>
<p class="muted small">This app records refunds. The money itself is sent from your Paystack dashboard, mobile money wallet or bank, then marked as sent here so the customer is emailed and your reports stay right.</p>
<?php require __DIR__ . '/_footer.php'; ?>
