<?php
require __DIR__ . '/includes/bootstrap.php';

$error = '';
$ref = strtoupper(trim(val('ref')));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim(val('email')));
    $order = $ref !== '' ? find_order_by_ref($ref) : null;
    $tries = (int) ($_SESSION['track_fails'] ?? 0);
    if (($_SESSION['track_lock'] ?? 0) > time()) {
        $error = 'Too many attempts. Wait a minute, then try again.';
    } elseif ($order && hash_equals(strtolower($order['email']), $email)) {
        unset($_SESSION['track_fails']);
        remember_guest_order($order['order_ref']);
        redirect('order.php?ref=' . urlencode($order['order_ref']));
    } else {
        $_SESSION['track_fails'] = ++$tries;
        if ($tries >= 5) {
            $_SESSION['track_lock'] = time() + 60;
            $_SESSION['track_fails'] = 0;
        }
        $error = 'We could not find an order with that reference and email. Check them and try again.';
    }
}

$pageTitle = 'Track your order';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap narrow section">
    <h1>Track your order</h1>
    <p class="muted">Enter the order reference from your confirmation and the email you used at checkout.</p>
    <?php if ($error): ?><p class="flash flash-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('track.php')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="ref">Order reference</label>
            <input id="ref" type="text" name="ref" value="<?= e($ref) ?>" placeholder="VB-261110-A1B2C3" required>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="<?= e(val('email')) ?>" autocomplete="email" required>
        </div>
        <button class="btn btn-primary" type="submit">Find my order</button>
    </form>
    <p class="muted small">Have an account? <a class="text-link" href="<?= e(url('login.php?next=account.php')) ?>">Log in</a> to see all your orders.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
