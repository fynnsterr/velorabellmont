<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ok = enquiry_set_status((int) val('id'), val('status'));
    flash($ok ? 'success' : 'error', $ok ? 'Enquiry updated.' : 'Could not update that enquiry.');
    redirect('admin/enquiries.php' . (val('view') !== '' ? '?status=' . urlencode(val('view')) : ''));
}

$status = in_array(val('status'), ['new', 'replied', 'closed'], true) ? val('status') : 'all';
$q = trim(val('q'));
$rows = enquiries_list($status, $q);
$topics = contact_topics();
$counts = ['new' => 0, 'replied' => 0, 'closed' => 0];
foreach (db()->query('SELECT status, COUNT(*) AS n FROM vb_messages GROUP BY status')->fetchAll() as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$pageTitle = 'Enquiries';
require __DIR__ . '/_header.php';
?>
<div class="page-head"><h1>Enquiries</h1><p class="muted">Messages sent from the Contact page. Reply by email, then mark them replied.</p></div>
<div class="pills">
    <?php foreach (['all' => 'All', 'new' => 'New (' . $counts['new'] . ')', 'replied' => 'Replied (' . $counts['replied'] . ')', 'closed' => 'Closed (' . $counts['closed'] . ')'] as $k => $label): ?>
        <a class="pill<?= $status === $k ? ' is-active' : '' ?>" href="<?= e(url('admin/enquiries.php' . ($k === 'all' ? '' : '?status=' . $k))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
<form method="get" action="<?= e(url('admin/enquiries.php')) ?>" class="filter-form">
    <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <label class="sr-only" for="q">Search</label>
    <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email, order or words in the message">
    <button class="btn btn-sm" type="submit">Search</button>
</form>

<?php if (!$rows): ?>
    <p class="muted">No enquiries here.</p>
<?php endif; ?>
<?php foreach ($rows as $m):
    $orderId = null;
    if ($m['order_ref']) {
        $o = find_order_by_ref((string) $m['order_ref']);
        $orderId = $o ? (int) $o['id'] : null;
    }
    $waPhone = $m['phone'] ? wa_normalise((string) $m['phone']) : '';
    ?>
    <article class="enquiry" id="m<?= (int) $m['id'] ?>">
        <p><strong><?= e($m['name']) ?></strong> <span class="badge badge-<?= $m['status'] === 'new' ? 'pending' : ($m['status'] === 'replied' ? 'paid' : 'cancelled') ?>"><?= e(ucfirst($m['status'])) ?></span></p>
        <p class="meta"><?= e($topics[$m['topic']] ?? $m['topic']) ?> · <?= e(date('j M Y, g:i a', strtotime((string) $m['created_at']))) ?><?php if ($m['order_ref']): ?> · Order <?php if ($orderId): ?><a href="<?= e(url('admin/order.php?id=' . $orderId)) ?>"><?= e($m['order_ref']) ?></a><?php else: ?><?= e($m['order_ref']) ?> (not found)<?php endif; ?><?php endif; ?></p>
        <pre><?= e($m['message']) ?></pre>
        <p class="meta">
            <a href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: your message to ' . cfg('site.name'))) ?>"><?= e($m['email']) ?></a>
            <?php if ($m['phone']): ?> · <?= e($m['phone']) ?><?php endif; ?>
            <?php if ($waPhone !== ''): ?> · <a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" rel="noopener">WhatsApp<span class="sr-only"> (opens in a new tab)</span></a><?php endif; ?>
        </p>
        <form method="post" action="<?= e(url('admin/enquiries.php')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <input type="hidden" name="view" value="<?= e($status === 'all' ? '' : $status) ?>">
            <?php if ($m['status'] !== 'replied'): ?><button class="btn btn-sm" type="submit" name="status" value="replied">Mark replied</button><?php endif; ?>
            <?php if ($m['status'] !== 'closed'): ?><button class="btn btn-sm" type="submit" name="status" value="closed">Close</button><?php endif; ?>
            <?php if ($m['status'] !== 'new'): ?><button class="btn btn-sm" type="submit" name="status" value="new">Reopen</button><?php endif; ?>
        </form>
    </article>
<?php endforeach; ?>
<?php require __DIR__ . '/_footer.php'; ?>
