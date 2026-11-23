<?php
require __DIR__ . '/includes/bootstrap.php';

$days = return_days();
$pageTitle = 'Returns and exchanges';
$metaDesc = 'Return unworn jewelry within ' . $days . ' days for a refund or exchange. How to start a return, what we cannot take back and how refunds are sent.';
$jsonld = [breadcrumb_jsonld([['Home', abs_url('')], ['Returns and exchanges', abs_url('returns.php')]])];
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section page-content prose">
    <p class="eyebrow eyebrow-dark">Returns</p>
    <h1>Returns and exchanges</h1>
    <p class="lede">Not right? Return unworn pieces within <?= (int) $days ?> days of delivery and we will refund you or exchange the piece.</p>

    <h2 id="eligible">What you can return</h2>
    <ul>
        <li>Pieces that are unworn, undamaged and in their original packaging, returned within <?= (int) $days ?> days of delivery. The days count from the day it is delivered to you.</li>
        <li>Rings in the wrong size, for an exchange in another size, if they are unworn.</li>
        <li>Pieces that arrive damaged, faulty or not what you ordered. Tell us within 48 hours of delivery, with a photo, and we will replace the piece at no cost.</li>
    </ul>

    <h2 id="promise">Our promise</h2>
    <?php $lines = warranty_lines(); if ($lines): ?>
    <ul>
        <?php foreach ($lines as $l): ?><li><?= e($l) ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p>The promise does not cover damage from accidents, from chemicals such as perfume, bleach or chlorine, or from wearing a piece while swimming, showering or exercising. See <a href="<?= e(url('care.php')) ?>">Materials and care</a> for how to look after your pieces.</p>

    <h2 id="not-eligible">What we cannot take back</h2>
    <ul>
        <li>Earrings that have been worn or opened, for hygiene reasons. Sealed, unworn earrings can be returned.</li>
        <li>Pieces that show signs of wear, damage after delivery, or have been altered.</li>
        <li>Gift cards.</li>
    </ul>

    <h2 id="how">How to start a return</h2>
    <ol class="steps-list">
        <li><strong>Contact us</strong> through the <a href="<?= e(url('contact.php')) ?>">contact page</a> or WhatsApp with your order number and the piece you want to return.</li>
        <li><strong>We confirm</strong> that it can be returned and tell you where to send it.</li>
        <li><strong>Send it back</strong> in its original packaging. Please keep proof of postage or delivery.</li>
        <li><strong>We check it</strong> when it arrives and approve the refund or exchange.</li>
    </ol>

    <h2 id="refunds">Refunds</h2>
    <p>Once we have received and checked your return, we aim to send your refund within <?= (int) refund_days() ?> working days, to the method you paid with where we can (card, mobile money or Paystack). You will get an email when it has been sent. Original delivery fees are not refunded unless the piece was faulty or wrong.</p>
    <p>Who pays for the return delivery: you do, unless the piece was faulty or not what you ordered.</p>

    <h2 id="exchanges">Exchanges</h2>
    <p>We can exchange a piece for another size or for a different piece of the same value, subject to stock. If the new piece costs more, you pay the difference. If it costs less, we refund the difference.</p>

    <p><a class="btn btn-primary" href="<?= e(url('contact.php?topic=return')) ?>">Start a return</a></p>
    <?php if (policies_updated() !== ''): ?><p class="muted small">Last updated <?= e(policies_updated()) ?></p><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
