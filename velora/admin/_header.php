<?php
$admin = require_admin();
$fullTitle = ($pageTitle ?? 'Admin') . ' | Velora admin';
$here = basename($_SERVER['SCRIPT_NAME']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($fullTitle) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Marcellus&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="admin">
<header class="admin-bar">
    <div class="wrap bar">
        <a class="brand" href="<?= e(url('admin/index.php')) ?>">Velora admin</a>
        <nav class="admin-nav" aria-label="Admin">
            <a href="<?= e(url('admin/index.php')) ?>"<?= $here === 'index.php' ? ' aria-current="page"' : '' ?>>Overview</a>
            <a href="<?= e(url('admin/products.php')) ?>"<?= in_array($here, ['products.php', 'product_edit.php'], true) ? ' aria-current="page"' : '' ?>>Products</a>
            <a href="<?= e(url('admin/orders.php')) ?>"<?= in_array($here, ['orders.php', 'order.php'], true) ? ' aria-current="page"' : '' ?>>Orders</a>
            <a href="<?= e(url('index.php')) ?>">View shop</a>
        </nav>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="link-button">Log out</button>
        </form>
    </div>
</header>
<main id="main" class="wrap admin-main">
<?php foreach (take_flashes() as $f): ?>
    <p class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></p>
<?php endforeach; ?>
