<?php require VB_ROOT . '/includes/header.php'; ?>
<section class="wrap narrow section">
    <h1><?= e($errorTitle) ?></h1>
    <p class="lede"><?= e($errorMessage) ?></p>
    <p><a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Browse all jewelry</a></p>
</section>
<?php require VB_ROOT . '/includes/footer.php'; ?>
