<?php
require __DIR__ . '/includes/bootstrap.php';

$totals = db()->query('SELECT COUNT(*) AS n, AVG(rating) AS a FROM vb_reviews')->fetch();
$reviewCount = (int) $totals['n'];
$reviewAvg = $reviewCount ? round((float) $totals['a'], 1) : 0.0;
$photos = packaging_photos();
$story = trim((string) cfg('site.about_story', ''));
$cats = categories();

$pageTitle = 'About us';
$metaDesc = 'Velora Bellmont is an online jewelry store for Ghana: stylish, elegant and affordable necklaces, bracelets, rings and earrings, with honest descriptions, clear prices and delivery to every region.';
$jsonld = [organization_jsonld(), breadcrumb_jsonld([['Home', abs_url('')], ['About us', abs_url('about.php')]])];
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section page-content">
    <p class="eyebrow eyebrow-dark">About us</p>
    <h1>Jewelry for every occasion, delivered across Ghana</h1>
    <p class="lede">Velora Bellmont is an online jewelry store. We sell necklaces, bracelets, rings, earrings and statement pieces that are stylish, elegant and affordable, for everyday wear and for the days that matter.</p>

    <?php if ($story !== ''): ?>
        <h2>Our story</h2>
        <?php foreach (preg_split('/\n\s*\n/', $story) as $para): ?><p><?= e(trim($para)) ?></p><?php endforeach; ?>
    <?php endif; ?>

    <h2>What we sell</h2>
    <p>Every piece in the shop is chosen to look good, feel comfortable and sit within reach. You can browse by type:</p>
    <ul class="plain-list inline-list">
        <?php foreach ($cats as $c): ?><li><a href="<?= e(url('shop.php?category=' . $c['slug'])) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
        <li><a href="<?= e(url('collections.php')) ?>">Collections</a></li>
        <li><a href="<?= e(url('giftfinder.php')) ?>">Gift finder</a></li>
    </ul>

    <h2>How we describe our jewelry</h2>
    <p>We would rather be clear than impressive. The finish names we use, such as gold tone, rose gold tone and silver tone, describe the colour of a piece. They are not claims about the metal underneath. We only name a specific material, such as sterling silver, gold plating or stainless steel, on a product page after we have confirmed it with our supplier. Our <a href="<?= e(url('care.php')) ?>">materials and care guide</a> explains the terms and how to make your jewelry last.</p>

    <h2>What you can count on</h2>
    <ul class="promise-list">
        <li><strong>Clear prices.</strong> Every price is in Ghana cedis. Delivery is shown before you pay.</li>
        <li><strong>Delivery to every region.</strong> See the <a href="<?= e(url('delivery.php')) ?>">delivery fees and timelines</a>.</li>
        <li><strong>Easy returns.</strong> Return unworn pieces within <?= (int) return_days() ?> days. Read the <a href="<?= e(url('returns.php')) ?>">returns and exchanges policy</a>.</li>
        <li><strong>Safe payment.</strong> Card and mobile money are handled by Paystack. We never see or store your card details.</li>
        <li><strong>Reviews from real buyers.</strong> Only customers who bought and received a piece can review it, and every review is marked as a verified buyer.</li>
        <li><strong>A person to talk to.</strong> Message us on WhatsApp or through the <a href="<?= e(url('contact.php')) ?>">contact page</a>.</li>
    </ul>

    <?php if ($reviewCount > 0): ?>
        <h2>What customers say</h2>
        <p><span class="stars" aria-hidden="true"><?= e(stars($reviewAvg)) ?></span> <strong><?= e(number_format($reviewAvg, 1)) ?> out of 5</strong> from <?= $reviewCount ?> verified buyer review<?= $reviewCount === 1 ? '' : 's' ?>. <a href="<?= e(url('reviews.php')) ?>">Read them all</a>.</p>
    <?php endif; ?>

    <?php if ($photos): ?>
        <h2>How your order arrives</h2>
        <p>Every order is packed to be opened, and gifted.</p>
        <ul class="packaging" aria-label="Our packaging">
            <?php foreach ($photos as $ph): ?>
                <li><?= img_html($ph['image'], $ph['alt'], ['width' => 600, 'height' => 600, 'sizes' => '(max-width: 700px) 50vw, 280px']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Talk to us</h2>
    <p>Questions about a piece, a gift or an order? <a href="<?= e(url('contact.php')) ?>">Contact us</a> or read the <a href="<?= e(url('faq.php')) ?>">frequently asked questions</a>.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
