<?php
$soldOut = (int) $p['stock'] < 1;
$link = url('product.php?slug=' . urlencode($p['slug']));
?>
<article class="card">
    <a class="card-media" href="<?= e($link) ?>">
        <img src="<?= e(asset($p['image'])) ?>" alt="<?= e($p['name']) ?>" width="800" height="800" loading="lazy">
        <?php if ($soldOut): ?><span class="card-flag">Sold out</span><?php endif; ?>
    </a>
    <div class="card-body">
        <h3 class="card-title"><a href="<?= e($link) ?>"><?= e($p['name']) ?></a></h3>
        <p class="card-meta"><?= e($p['metal']) ?></p>
        <div class="card-foot">
            <p class="price"><?= e(money($p['price'])) ?></p>
            <?php if (!$soldOut): ?>
                <form method="post" action="<?= e(url('cart.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                    <input type="hidden" name="qty" value="1">
                    <button type="submit" class="btn btn-sm">Add to cart</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
