<?php
require __DIR__ . '/includes/bootstrap.php';

$verified = materials_verified_list();
$pageTitle = 'Materials and jewelry care';
$metaDesc = 'How to care for plated and fashion jewelry, what gold tone, rose gold tone and silver tone mean, and which materials Velora Bellmont has verified.';
$jsonld = [breadcrumb_jsonld([['Home', abs_url('')], ['Materials and care', abs_url('care.php')]])];
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section page-content prose">
    <p class="eyebrow eyebrow-dark">Materials and care</p>
    <h1>Materials and jewelry care</h1>
    <p class="lede">What our finish names mean, which materials we have confirmed, and how to keep your pieces looking their best.</p>

    <h2 id="finishes">What our finish names mean</h2>
    <p>Most of our jewelry is described by the colour of its finish. These are colour descriptions, not claims about the metal.</p>
    <dl class="terms">
        <?php foreach (finish_terms() as $t): ?>
            <div><dt><?= e($t['term']) ?></dt><dd><?= e($t['meaning']) ?></dd></div>
        <?php endforeach; ?>
    </dl>

    <h2 id="verified">Verified materials</h2>
    <?php if ($verified): ?>
        <p>For these materials we have confirmed the specification with our supplier. They are shown on the product pages they apply to:</p>
        <ul>
            <?php foreach ($verified as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>We have not published verified material specifications yet. We will name a specific material, such as sterling silver, gold plating or stainless steel, on a product page only after we have confirmed it with our supplier. Until then a product page shows the finish colour only.</p>
    <?php endif; ?>
    <p>If you have a skin sensitivity or an allergy, ask us about a piece before you buy. We will tell you what we know and what we do not.</p>

    <?php foreach (care_sections() as $sec): ?>
        <h2 id="<?= e($sec['id']) ?>"><?= e($sec['title']) ?></h2>
        <ul>
            <?php foreach ($sec['items'] as $i): ?><li><?= e($i) ?></li><?php endforeach; ?>
        </ul>
    <?php endforeach; ?>

    <h2 id="sizing">Ring sizing</h2>
    <p>Pick your size on the product page. If you are between two sizes, choose the larger one. Fingers are slightly larger in the evening and in warm weather. If a ring does not fit, we can exchange it for another size within <?= (int) return_days() ?> days if it is unworn. See <a href="<?= e(url('returns.php')) ?>">Returns and exchanges</a>.</p>

    <p><a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Shop jewelry</a> <a class="btn" href="<?= e(url('contact.php')) ?>">Ask us a question</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
