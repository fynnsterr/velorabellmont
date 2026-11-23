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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Instrument+Sans:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="admin">
<header class="admin-bar">
    <div class="wrap bar">
        <a class="brand" href="<?= e(url('admin/index.php')) ?>">Velora admin</a>
        <nav class="admin-nav" aria-label="Admin">
            <?php
            $links = [
                ['index.php', ['index.php'], 'Overview'],
                ['orders.php', ['orders.php', 'order.php'], 'Orders'],
                ['refunds.php', ['refunds.php'], 'Refunds'],
                ['products.php', ['products.php', 'product_edit.php'], 'Products'],
                ['inventory.php', ['inventory.php', 'stock.php'], 'Inventory'],
                ['customers.php', ['customers.php', 'customer.php'], 'Customers'],
                ['analytics.php', ['analytics.php'], 'Analytics'],
                ['reports.php', ['reports.php'], 'Reports'],
                ['promos.php', ['promos.php'], 'Discounts'],
                ['collections.php', ['collections.php', 'collection_edit.php'], 'Collections'],
                ['giftcards.php', ['giftcards.php'], 'Gift cards'],
                ['reviews.php', ['reviews.php'], 'Reviews'],
                ['gallery.php', ['gallery.php'], 'Gallery'],
                ['campaigns.php', ['campaigns.php', 'campaign_edit.php'], 'Campaigns'],
                ['enquiries.php', ['enquiries.php'], 'Enquiries'],
                ['messages.php', ['messages.php'], 'Mail log'],
                ['traffic.php', ['traffic.php'], 'Traffic'],
                ['bulk.php', ['bulk.php'], 'Bulk tools'],
                ['staff.php', ['staff.php'], 'Staff'],
                ['activity.php', ['activity.php'], 'Activity'],
            ];
            $newEnq = enquiry_new_count();
            foreach ($links as [$file, $group, $label]):
                if (!staff_can($admin['role'], $file)) {
                    continue;
                } ?>
                <a href="<?= e(url('admin/' . $file)) ?>"<?= in_array($here, $group, true) ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($file === 'enquiries.php' && $newEnq > 0): ?><span class="tag-new"><?= (int) $newEnq ?><span class="sr-only"> new</span></span><?php endif; ?></a>
            <?php endforeach; ?>
            <a href="<?= e(url('index.php')) ?>">View shop</a>
        </nav>
        <a class="admin-pw" href="<?= e(url('password.php')) ?>">Change password</a>
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
