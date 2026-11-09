<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) val('id');
    if (val('action') === 'delete' && $id > 0) {
        $st = db()->prepare('SELECT image FROM vb_products WHERE id = ?');
        $st->execute([$id]);
        $img = (string) $st->fetchColumn();
        db()->prepare('DELETE FROM vb_products WHERE id = ?')->execute([$id]);
        if (strpos($img, 'uploads/') === 0) {
            $file = VB_ROOT . '/assets/uploads/' . basename($img);
            if (is_file($file)) {
                @unlink($file);
            }
        }
        flash('success', 'Product deleted. Past orders keep its name and price.');
    }
    redirect('admin/products.php');
}

$pageTitle = 'Products';
require __DIR__ . '/_header.php';
$products = db()->query('SELECT p.*, c.name AS category_name FROM vb_products p JOIN vb_categories c ON c.id = p.category_id ORDER BY p.id DESC')->fetchAll();
?>
<div class="page-head row-between">
    <h1>Products</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/product_edit.php')) ?>">Add product</a>
</div>

<table class="table">
    <thead><tr><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th>Shown</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
        <tr>
            <td class="cell-product">
                <img src="<?= e(asset($p['image'])) ?>" alt="" width="800" height="800">
                <a href="<?= e(url('admin/product_edit.php?id=' . (int) $p['id'])) ?>"><?= e($p['name']) ?></a>
            </td>
            <td><?= e($p['category_name']) ?></td>
            <td class="num"><?= e(money($p['price'])) ?></td>
            <td class="num"><?= (int) $p['stock'] ?></td>
            <td><?= (int) $p['active'] ? 'Yes' : 'Hidden' ?><?= (int) $p['featured'] ? ', featured' : '' ?></td>
            <td class="actions">
                <a href="<?= e(url('admin/product_edit.php?id=' . (int) $p['id'])) ?>">Edit</a>
                <form method="post" action="<?= e(url('admin/products.php')) ?>" class="inline-form" data-confirm="Delete <?= e($p['name']) ?>? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="link-button link-danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php require __DIR__ . '/_footer.php'; ?>
