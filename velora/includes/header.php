<?php
$user = current_user();
$cartCount = cart_count();
$navCats = categories();
$fullTitle = isset($pageTitle) ? $pageTitle . ' | ' . cfg('site.name') : cfg('site.name') . ' | Jewelry for every occasion';
$metaDesc = $metaDesc ?? 'Stylish, elegant and affordable necklaces, bracelets, rings, earrings and statement pieces. Shop online and get it delivered anywhere in Ghana.';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Marcellus&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
    <div class="wrap bar">
        <a class="brand" href="<?= e(url('index.php')) ?>">Velora Bellmont</a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
        <nav id="site-nav" class="site-nav" aria-label="Main">
            <a href="<?= e(url('shop.php')) ?>">All jewelry</a>
            <?php foreach ($navCats as $c): ?>
                <a href="<?= e(url('shop.php?category=' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="bar-actions">
            <?php if ($user): ?>
                <a href="<?= e(url($user['role'] === 'admin' ? 'admin/index.php' : 'account.php')) ?>"><?= $user['role'] === 'admin' ? 'Admin' : 'My orders' ?></a>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Log out</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('login.php')) ?>">Log in</a>
            <?php endif; ?>
            <a class="cart-link" href="<?= e(url('cart.php')) ?>">Cart<span class="cart-count" aria-label="<?= (int) $cartCount ?> items in cart"><?= (int) $cartCount ?></span></a>
        </div>
    </div>
</header>
<main id="main">
<?php foreach (take_flashes() as $f): ?>
    <div class="wrap"><p class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></p></div>
<?php endforeach; ?>
