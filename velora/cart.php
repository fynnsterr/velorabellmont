<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $pid = (int) ($_POST['product_id'] ?? 0);
        $qty = (int) ($_POST['qty'] ?? 1);
        $st = db()->prepare('SELECT id, name, stock FROM vb_products WHERE id = ? AND active = 1');
        $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p) {
            flash('error', 'That piece is no longer available.');
        } elseif ((int) $p['stock'] < 1) {
            flash('error', $p['name'] . ' is sold out.');
        } else {
            cart_add($pid, $qty);
            $inCart = (int) $_SESSION['cart'][$pid];
            if ($inCart > (int) $p['stock']) {
                $_SESSION['cart'][$pid] = (int) $p['stock'];
                flash('info', 'Only ' . (int) $p['stock'] . ' of ' . $p['name'] . ' in stock. We set your cart to that amount.');
            } else {
                flash('success', $p['name'] . ' is in your cart.');
            }
        }
    } elseif ($action === 'update') {
        $qtys = $_POST['qty'] ?? [];
        if (is_array($qtys)) {
            foreach ($qtys as $pid => $qty) {
                cart_set((int) $pid, (int) $qty);
            }
        }
        flash('success', 'Cart updated.');
    } elseif ($action === 'remove') {
        cart_set((int) ($_POST['product_id'] ?? 0), 0);
        flash('success', 'Removed from your cart.');
    }
    redirect('cart.php');
}

$lines = cart_lines();
$totals = cart_totals($lines);
$pageTitle = 'Your cart';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section">
    <div class="page-head"><h1>Your cart</h1></div>

    <?php if (!$lines): ?>
        <div class="empty">
            <h2>Your cart is empty</h2>
            <p>Pick a piece you love and it will show up here.</p>
            <p><a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Browse all jewelry</a></p>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <form method="post" action="<?= e(url('cart.php')) ?>" class="cart-lines">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <ul class="line-list">
                    <?php foreach ($lines as $l): $p = $l['product']; ?>
                        <li class="line">
                            <a class="line-img" href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>"><img src="<?= e(asset($p['image'])) ?>" alt="" width="800" height="800"></a>
                            <div class="line-main">
                                <a class="line-name" href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>"><?= e($p['name']) ?></a>
                                <p class="muted"><?= e($p['metal']) ?> · <?= e(money($p['price'])) ?> each</p>
                                <div class="line-actions">
                                    <label class="sr-only" for="qty-<?= (int) $p['id'] ?>">Quantity for <?= e($p['name']) ?></label>
                                    <input id="qty-<?= (int) $p['id'] ?>" class="qty" type="number" name="qty[<?= (int) $p['id'] ?>]" value="<?= (int) $l['qty'] ?>" min="0" max="<?= min(20, (int) $p['stock']) ?>">
                                    <button type="submit" class="link-button" name="action" value="remove" form="remove-<?= (int) $p['id'] ?>">Remove</button>
                                </div>
                            </div>
                            <p class="line-total"><?= e(money($l['line_total'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p><button type="submit" class="btn">Update cart</button></p>
            </form>
            <?php foreach ($lines as $l): ?>
                <form id="remove-<?= (int) $l['product']['id'] ?>" method="post" action="<?= e(url('cart.php')) ?>" class="hidden-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= (int) $l['product']['id'] ?>">
                </form>
            <?php endforeach; ?>

            <aside class="summary" aria-label="Order summary">
                <h2>Order summary</h2>
                <dl class="sum-rows">
                    <div><dt>Subtotal</dt><dd><?= e(money($totals['subtotal'])) ?></dd></div>
                    <div><dt>Shipping</dt><dd><?= $totals['shipping'] > 0 ? e(money($totals['shipping'])) : 'Free' ?></dd></div>
                    <div class="sum-total"><dt>Total</dt><dd><?= e(money($totals['total'])) ?></dd></div>
                </dl>
                <?php if ($totals['shipping'] > 0): ?>
                    <p class="muted small">Add <?= e(money((float) cfg('shipping.free_over') - $totals['subtotal'])) ?> more for free shipping.</p>
                <?php endif; ?>
                <a class="btn btn-primary btn-block" href="<?= e(url('checkout.php')) ?>">Go to checkout</a>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
