<?php
require __DIR__ . '/includes/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$st = db()->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM vb_products p JOIN vb_categories c ON c.id = p.category_id WHERE p.slug = ? AND p.active = 1');
$st->execute([$slug]);
$product = $st->fetch();

if (!$product) {
    render_error_page(404, 'We could not find that piece', 'It may have been removed or the link is wrong.');
}

$rel = db()->prepare('SELECT * FROM vb_products WHERE active = 1 AND category_id = ? AND id <> ? ORDER BY id LIMIT 4');
$rel->execute([(int) $product['category_id'], (int) $product['id']]);
$related = $rel->fetchAll();

$stock = (int) $product['stock'];
$pageTitle = $product['name'];
$metaDesc = mb_substr($product['description'], 0, 150);
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('shop.php')) ?>">All jewelry</a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('shop.php?category=' . $product['category_slug'])) ?>"><?= e($product['category_name']) ?></a>
    </nav>

    <div class="product">
        <div class="product-media">
            <img src="<?= e(asset($product['image'])) ?>" alt="<?= e($product['name']) ?>" width="800" height="800">
        </div>
        <div class="product-info">
            <h1><?= e($product['name']) ?></h1>
            <p class="price price-lg"><?= e(money($product['price'])) ?></p>
            <p class="lede"><?= e($product['description']) ?></p>

            <?php if ($stock > 0): ?>
                <form method="post" action="<?= e(url('cart.php')) ?>" class="buy">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <label for="qty">Quantity</label>
                    <input id="qty" class="qty" type="number" name="qty" value="1" min="1" max="<?= min(20, $stock) ?>">
                    <button class="btn btn-primary btn-lg" type="submit">Add to cart</button>
                </form>
                <?php if ($stock <= 5): ?><p class="notice">Only <?= $stock ?> left in stock.</p><?php endif; ?>
            <?php else: ?>
                <p class="notice notice-out">This piece is sold out. Browse <a href="<?= e(url('shop.php?category=' . $product['category_slug'])) ?>"><?= e(strtolower($product['category_name'])) ?></a> for something similar.</p>
            <?php endif; ?>

            <dl class="specs">
                <div><dt>Metal</dt><dd><?= e($product['metal']) ?></dd></div>
                <div><dt>Category</dt><dd><?= e($product['category_name']) ?></dd></div>
                <div><dt>Delivery</dt><dd>Across Ghana. Free over <?= e(money(cfg('shipping.free_over'))) ?>.</dd></div>
                <div><dt>Returns</dt><dd>Unworn pieces within 7 days.</dd></div>
            </dl>
        </div>
    </div>
</section>

<?php if ($related): ?>
<section class="section wrap">
    <div class="section-head"><h2>More <?= e(strtolower($product['category_name'])) ?></h2></div>
    <div class="grid-products">
        <?php foreach ($related as $p): require __DIR__ . '/includes/product_card.php'; endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
