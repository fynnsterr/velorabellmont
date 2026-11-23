<div class="trust-bar">
    <div class="wrap">
        <ul class="trust-strip" aria-label="Why shop with us">
            <?php foreach (trust_points() as $tp): ?>
                <li><a href="<?= e(url($tp['href'])) ?>"><?= e($tp['title']) ?></a><span class="t-text"><?= e($tp['text']) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
