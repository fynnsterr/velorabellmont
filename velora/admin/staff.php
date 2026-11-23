<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $r = staff_set_role(val('email'), val('role'), (int) $admin['id']);
    flash($r[0] ? 'success' : 'error', $r[1]);
    redirect('admin/staff.php');
}

$pageTitle = 'Staff';
require __DIR__ . '/_header.php';
$staff = staff_list();
$blurb = [
    'admin' => 'Everything.',
    'fulfilment' => 'Orders (status and shipping), packing slips, labels, inventory.',
    'support' => 'View orders, customers, enquiries, reviews and the mail log. Cannot change orders or refund.',
    'marketing' => 'Products, collections, gallery, campaigns, discounts, analytics, reports, traffic.',
];
?>
<div class="page-head"><h1>Staff</h1><p class="muted">Give people on your team their own login with only the pages they need.</p></div>

<table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th><span class="sr-only">Change</span></th></tr></thead>
    <tbody>
    <?php foreach ($staff as $s): ?>
        <tr>
            <td><?= e($s['name']) ?></td>
            <td><?= e($s['email']) ?></td>
            <td><?= e(staff_roles()[$s['role']] ?? $s['role']) ?></td>
            <td>
                <form method="post" action="<?= e(url('admin/staff.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="email" value="<?= e($s['email']) ?>">
                    <label class="sr-only" for="r<?= (int) $s['id'] ?>">Role</label>
                    <select id="r<?= (int) $s['id'] ?>" name="role">
                        <?php foreach (staff_roles() as $k => $label): ?><option value="<?= e($k) ?>"<?= $s['role'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        <option value="customer">Remove from staff</option>
                    </select>
                    <button type="submit" class="btn btn-small">Save</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Add a team member</h2>
<p class="muted">They must first create a normal account on the shop with their own email. Then enter that email here and pick a role.</p>
<form method="post" action="<?= e(url('admin/staff.php')) ?>" class="form-card">
    <?= csrf_field() ?>
    <div class="field"><label for="email">Their account email</label><input id="email" type="email" name="email" required maxlength="190"></div>
    <div class="field"><label for="role">Role</label>
        <select id="role" name="role">
            <?php foreach (staff_roles() as $k => $label): ?><option value="<?= e($k) ?>"<?= $k === 'fulfilment' ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Add</button>
</form>

<h2>What each role can open</h2>
<ul>
<?php foreach (staff_roles() as $k => $label): ?><li><strong><?= e($label) ?>:</strong> <?= e($blurb[$k]) ?></li><?php endforeach; ?>
</ul>
<?php require __DIR__ . '/_footer.php'; ?>
