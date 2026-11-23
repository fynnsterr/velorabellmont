<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) val('id');
    if (val('action') === 'delete_review' && $id > 0) {
        $ph = db()->prepare('SELECT photo FROM vb_reviews WHERE id = ?');
        $ph->execute([$id]);
        upload_image_remove($ph->fetchColumn() ?: null);
        db()->prepare('DELETE FROM vb_reviews WHERE id = ?')->execute([$id]);
        flash('success', 'Review deleted.');
    } elseif (val('action') === 'remove_photo' && $id > 0) {
        $ph = db()->prepare('SELECT photo FROM vb_reviews WHERE id = ?');
        $ph->execute([$id]);
        upload_image_remove($ph->fetchColumn() ?: null);
        db()->prepare('UPDATE vb_reviews SET photo = NULL WHERE id = ?')->execute([$id]);
        flash('success', 'Photo removed. The review text stays.');
    } elseif (val('action') === 'delete_subscriber' && $id > 0) {
        db()->prepare('DELETE FROM vb_subscribers WHERE id = ?')->execute([$id]);
        flash('success', 'Subscriber removed.');
    }
    redirect('admin/reviews.php');
}

$pageTitle = 'Reviews and subscribers';
require __DIR__ . '/_header.php';
$reviews = db()->query('SELECT r.*, u.name AS user_name, p.name AS product_name FROM vb_reviews r JOIN vb_users u ON u.id = r.user_id JOIN vb_products p ON p.id = r.product_id ORDER BY r.created_at DESC, r.id DESC')->fetchAll();
$subs = db()->query('SELECT * FROM vb_subscribers ORDER BY created_at DESC, id DESC')->fetchAll();
?>
<div class="page-head"><h1>Reviews and subscribers</h1></div>

<h2>Customer reviews</h2>
<?php if (!$reviews): ?>
    <p class="muted">No reviews yet. Customers can review a piece after buying it.</p>
<?php else: ?>
<table class="table">
    <thead><tr><th>Piece</th><th>Customer</th><th>Rating</th><th>Comment</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($reviews as $r): ?>
        <tr>
            <td><?= e($r['product_name']) ?></td>
            <td><?= e(reviewer_label($r['user_name'])) ?></td>
            <td><?= (int) $r['rating'] ?> / 5</td>
            <td><?= e($r['comment']) ?><?php if (!empty($r['photo'])): ?><br><a href="<?= e(asset($r['photo'])) ?>" target="_blank" rel="noopener"><?= img_html($r['photo'], 'Customer photo', ['width' => 60, 'height' => 60, 'sizes' => '60px', 'class' => 'thumb']) ?></a>
                <form method="post" class="inline-form" data-confirm="Remove this photo?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_photo"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button type="submit" class="link-button link-danger">Remove photo</button></form><?php endif; ?></td>
            <td class="actions">
                <form method="post" class="inline-form" data-confirm="Delete this review?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_review">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="link-button link-danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2 class="space-top">Newsletter subscribers (<?= count($subs) ?>)</h2>
<?php if (!$subs): ?>
    <p class="muted">No subscribers yet.</p>
<?php else: ?>
<table class="table">
    <thead><tr><th>Email</th><th>Joined</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($subs as $s): ?>
        <tr>
            <td><?= e($s['email']) ?></td>
            <td><?= e(date('j M Y', strtotime((string) $s['created_at']))) ?></td>
            <td class="actions">
                <form method="post" class="inline-form" data-confirm="Remove this subscriber?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_subscriber">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button type="submit" class="link-button link-danger">Remove</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
