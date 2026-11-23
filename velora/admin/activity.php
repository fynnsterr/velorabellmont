<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pageTitle = 'Activity';
require __DIR__ . '/_header.php';
$who = (int) val('user');
$rows = activity_recent(300, $who);
$staff = staff_list();
?>
<div class="page-head"><h1>Activity</h1><p class="muted">Every change made in the admin area, newest first. Passwords and form contents are never recorded.</p></div>
<form method="get" action="<?= e(url('admin/activity.php')) ?>" class="filter-form">
    <label class="sr-only" for="user">Person</label>
    <select id="user" name="user">
        <option value="">Everyone</option>
        <?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= $who === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Filter</button>
</form>
<?php if (!$rows): ?>
    <p class="muted">Nothing recorded yet.</p>
<?php else: ?>
<table class="table">
    <thead><tr><th>When</th><th>Who</th><th>What</th><th>Where</th><th>Detail</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e(date('j M, g:i a', strtotime((string) $r['created_at']))) ?></td>
            <td><?= e($r['user_name']) ?> <span class="muted small"><?= e($r['role']) ?></span></td>
            <td><?= e($r['action']) ?></td>
            <td><?= e((string) $r['target']) ?></td>
            <td class="muted small"><?= e((string) $r['detail']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
