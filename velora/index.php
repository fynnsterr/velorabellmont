<?php
require __DIR__ . '/includes/bootstrap.php';

$hero = db()->query('SELECT * FROM vb_products WHERE active = 1 AND featured = 1 ORDER BY id DESC LIMIT 3')->fetchAll();
$featured = db()->query('SELECT * FROM vb_products WHERE active = 1 AND featured = 1 ORDER BY id LIMIT 6')->fetchAll();
$tiles = db()->query("SELECT c.name, c.slug,
    (SELECT p.image FROM vb_products p WHERE p.category_id = c.id AND p.active = 1 ORDER BY p.featured DESC, p.id LIMIT 1) AS image,
    (SELECT COUNT(*) FROM vb_products p WHERE p.category_id = c.id AND p.active = 1) AS total
    FROM vb_categories c ORDER BY c.sort_order, c.name")->fetchAll();

$pageTitle = null;
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="wrap hero-grid">
        <div class="hero-copy">
            <h1>Jewelry you will reach for every day.</h1>
            <p class="lede">Necklaces, bracelets, rings and earrings in gold, rose gold and silver tones. Priced for real life and delivered across Ghana.</p>
            <p class="hero-actions">
                <a class="btn btn-gold" href="<?= e(url('shop.php')) ?>">Shop all jewelry</a>
                <a class="btn btn-outline-light" href="<?= e(url('shop.php?category=statement-pieces')) ?>">See statement pieces</a>
            </p>
        </div>
        <div class="tray" aria-label="Featured pieces">
            <?php foreach ($hero as $i => $p): ?>
                <a class="tray-piece tray-piece-<?= $i + 1 ?>" href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>">
                    <img src="<?= e(asset($p['image'])) ?>" alt="<?= e($p['name']) ?>" width="800" height="800">
                    <span class="tag">
                        <span class="tag-name"><?= e($p['name']) ?></span>
                        <span class="tag-price"><?= e(money($p['price'])) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section wrap">
    <div class="section-head">
        <h2>Shop by piece</h2>
    </div>
    <div class="tiles">
        <?php foreach ($tiles as $t): ?>
            <a class="tile" href="<?= e(url('shop.php?category=' . $t['slug'])) ?>">
                <img src="<?= e(asset($t['image'])) ?>" alt="" width="800" height="800" loading="lazy">
                <span class="tile-name"><?= e($t['name']) ?></span>
                <span class="tile-count"><?= (int) $t['total'] ?> pieces</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section wrap">
    <div class="section-head">
        <h2>Customer favorites</h2>
        <a class="text-link" href="<?= e(url('shop.php')) ?>">View all jewelry</a>
    </div>
    <div class="grid-products">
        <?php foreach ($featured as $p): require __DIR__ . '/includes/product_card.php'; endforeach; ?>
    </div>
</section>

<section class="section wrap">
    <div class="facts">
        <div class="fact">
            <h3>Delivery across Ghana</h3>
            <p>Flat <?= e(money(cfg('shipping.flat'))) ?> shipping. Free on orders over <?= e(money(cfg('shipping.free_over'))) ?>.</p>
        </div>
        <div class="fact">
            <h3>Pay the way you prefer</h3>
            <p>Card or mobile money through Paystack, or pay when your order arrives.</p>
        </div>
        <div class="fact">
            <h3>Easy returns</h3>
            <p>Return unworn pieces in their packaging within 7 days.</p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
