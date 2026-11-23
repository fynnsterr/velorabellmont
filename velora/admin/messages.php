<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (val('action') === 'feedback') {
        $r = send_due_feedback_requests();
        flash('success', $r['claimed'] === 0 ? 'No delivered orders are due a feedback request yet.' : $r['claimed'] . ' feedback requests processed, ' . $r['sent'] . ' emailed.');
    }
    redirect('admin/messages.php');
}

$type = val('type');
$status = in_array(val('status'), ['sent', 'failed', 'skipped'], true) ? val('status') : '';
$where = [];
$params = [];
if (preg_match('/^[a-z_]{3,30}$/', $type)) {
    $where[] = 'mail_type = ?';
    $params[] = $type;
}
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
$st = db()->prepare('SELECT * FROM vb_mail_log' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 150');
$st->execute($params);
$rows = $st->fetchAll();
$cut = date('Y-m-d H:i:s', strtotime('-30 days'));
$cnt = db()->prepare('SELECT status, COUNT(*) AS n FROM vb_mail_log WHERE created_at >= ? GROUP BY status');
$cnt->execute([$cut]);
$counts = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
foreach ($cnt->fetchAll() as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$types = db()->query('SELECT DISTINCT mail_type FROM vb_mail_log ORDER BY mail_type')->fetchAll(PDO::FETCH_COLUMN);
$dueCut = date('Y-m-d H:i:s', time() - (int) cfg('feedback.after_days', 3) * 86400);
$due = db()->prepare("SELECT COUNT(*) FROM vb_orders WHERE kind = 'shop' AND order_status = 'delivered' AND feedback_sent_at IS NULL AND delivered_at IS NOT NULL AND delivered_at <= ?");
$due->execute([$dueCut]);
$dueCount = (int) $due->fetchColumn();
$pageTitle = 'Mail log';
require __DIR__ . '/_header.php';
?>
<div class="page-head"><h1>Mail log</h1><p class="muted">Every email the shop sends or tries to send, so you can see what reached customers.</p></div>
<?php if (!cfg('mail.enabled', true)): ?><p class="flash flash-info">Email sending is switched off in config.php. Messages are logged as skipped.</p><?php endif; ?>

<div class="stats">
    <div class="stat"><p class="stat-num"><?= (int) $counts['sent'] ?></p><p class="stat-label">Handed to the mail server, last 30 days</p></div>
    <div class="stat"><p class="stat-num"><?= (int) $counts['failed'] ?></p><p class="stat-label">Failed</p></div>
    <div class="stat"><p class="stat-num"><?= (int) $counts['skipped'] ?></p><p class="stat-label">Skipped (email off)</p></div>
    <div class="stat"><p class="stat-num"><?= $dueCount ?></p><p class="stat-label">Feedback requests due</p></div>
</div>

<form method="post" action="<?= e(url('admin/messages.php')) ?>" class="inline-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="feedback">
    <button class="btn" type="submit"<?= $dueCount === 0 ? ' disabled' : '' ?>>Send feedback requests now</button>
    <span class="muted small">Asks customers to review orders delivered <?= (int) cfg('feedback.after_days', 3) ?> or more days ago. A scheduled task can do this for you (see the README).</span>
</form>

<form method="get" action="<?= e(url('admin/messages.php')) ?>" class="filter-form">
    <label class="sr-only" for="type">Type</label>
    <select id="type" name="type"><option value="">All types</option><?php foreach ($types as $t): ?><option value="<?= e($t) ?>"<?= $type === $t ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $t))) ?></option><?php endforeach; ?></select>
    <label class="sr-only" for="status">Status</label>
    <select id="status" name="status"><option value="">Any status</option><?php foreach (['sent', 'failed', 'skipped'] as $s): ?><option value="<?= e($s) ?>"<?= $status === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm" type="submit">Filter</button>
</form>

<?php if ($rows): ?>
    <table class="table">
        <thead><tr><th>When</th><th>Type</th><th>To</th><th>Subject</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $m): ?>
            <tr>
                <td><?= e(date('j M, g:i a', strtotime((string) $m['created_at']))) ?></td>
                <td><?= e(ucfirst(str_replace('_', ' ', $m['mail_type']))) ?></td>
                <td><?= e($m['recipient']) ?></td>
                <td><?= !empty($m['order_id']) ? '<a href="' . e(url('admin/order.php?id=' . (int) $m['order_id'])) . '">' . e($m['subject']) . '</a>' : e($m['subject']) ?></td>
                <td><span class="badge badge-<?= $m['status'] === 'sent' ? 'delivered' : ($m['status'] === 'failed' ? 'cancelled' : 'pending') ?>"><?= e(ucfirst($m['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="empty"><h2>No messages yet</h2><p>Order confirmations, payment and delivery updates will be listed here.</p></div>
<?php endif; ?>
<p class="muted small">Sent means PHP handed the email to the server's mail system. It cannot confirm that the inbox received it. Newsletter campaigns are tracked on the Campaigns page.</p>
<?php require __DIR__ . '/_footer.php'; ?>
