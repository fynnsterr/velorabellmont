<?php
require __DIR__ . '/includes/bootstrap.php';

$groups = faq_items();
$pageTitle = 'Frequently asked questions';
$metaDesc = 'Answers about paying, delivery, tracking, returns, ring sizes and jewelry care at Velora Bellmont.';
$jsonld = [faq_jsonld($groups), breadcrumb_jsonld([['Home', abs_url('')], ['FAQ', abs_url('faq.php')]])];
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section page-content">
    <p class="eyebrow eyebrow-dark">Help</p>
    <h1>Frequently asked questions</h1>
    <p class="lede">Can't find what you need? <a href="<?= e(url('contact.php')) ?>">Contact us</a>.</p>
    <?php foreach ($groups as $title => $items): ?>
        <h2><?= e($title) ?></h2>
        <div class="faq">
            <?php foreach ($items as $it): ?>
                <details>
                    <summary><?= e($it['q']) ?></summary>
                    <p><?= e($it['a']) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <p class="muted">More detail: <a href="<?= e(url('delivery.php')) ?>">Delivery</a> · <a href="<?= e(url('returns.php')) ?>">Returns and exchanges</a> · <a href="<?= e(url('care.php')) ?>">Materials and care</a> · <a href="<?= e(url('policies.php')) ?>">Privacy and terms</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
