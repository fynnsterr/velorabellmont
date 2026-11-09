<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$st = db()->prepare('SELECT * FROM vb_orders WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$st->execute([(int) $user['id']]);
$orders = $st->fetchAll();

$pageTitle = 'My orders';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section">
    <div class="page-head">
        <h1>My orders</h1>
        <p class="muted"><?= e($user['name']) ?> · <?= e($user['email']) ?></p>
    </div>

    <?php if (!$orders): ?>
        <div class="empty">
            <h2>No orders yet</h2>
            <p>Your orders will appear here once you place one.</p>
            <p><a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Browse all jewelry</a></p>
        </div>
    <?php else: ?>
        <ul class="order-list">
            <?php foreach ($orders as $o): ?>
                <li class="order-row">
                    <div>
                        <a class="line-name" href="<?= e(url('order.php?ref=' . urlencode($o['order_ref']))) ?>"><?= e($o['order_ref']) ?></a>
                        <p class="muted small"><?= e(date('j F Y', strtotime($o['created_at']))) ?></p>
                    </div>
                    <div class="order-badges"><?= badge($o['order_status']) ?> <?= badge($o['payment_status']) ?></div>
                    <p class="line-total"><?= e(money($o['total'])) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
