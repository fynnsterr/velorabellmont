<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$lines = cart_lines();
if (!$lines) {
    flash('info', 'Your cart is empty. Add a piece before checking out.');
    redirect('shop.php');
}
$totals = cart_totals($lines);
$errors = [];
$codRegions = ['Greater Accra', 'Ashanti'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $f = [
        'full_name' => trim(val('full_name')),
        'email' => strtolower(trim(val('email'))),
        'phone' => trim(val('phone')),
        'address' => trim(val('address')),
        'city' => trim(val('city')),
        'region' => trim(val('region')),
        'notes' => trim(val('notes')),
        'payment_method' => val('payment_method'),
    ];

    if (mb_strlen($f['full_name']) < 2 || mb_strlen($f['full_name']) > 120) {
        $errors['full_name'] = 'Enter the name of the person receiving the order.';
    }
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address. Your receipt goes here.';
    }
    if (!preg_match('/^\+?[0-9 ]{9,15}$/', $f['phone'])) {
        $errors['phone'] = 'Enter a phone number the courier can call.';
    }
    if (mb_strlen($f['address']) < 5 || mb_strlen($f['address']) > 255) {
        $errors['address'] = 'Enter a delivery address with a landmark if you can.';
    }
    if (mb_strlen($f['city']) < 2 || mb_strlen($f['city']) > 80) {
        $errors['city'] = 'Enter your town or city.';
    }
    if (!in_array($f['region'], ghana_regions(), true)) {
        $errors['region'] = 'Choose your region.';
    }
    if (mb_strlen($f['notes']) > 500) {
        $errors['notes'] = 'Keep delivery notes under 500 characters.';
    }
    if (!in_array($f['payment_method'], ['paystack', 'cod'], true)) {
        $errors['payment_method'] = 'Choose how you want to pay.';
    } elseif ($f['payment_method'] === 'cod' && !in_array($f['region'], $codRegions, true)) {
        $errors['payment_method'] = 'Pay on delivery is only available in Greater Accra and Ashanti. Pay by card or mobile money instead.';
    }

    if (!$errors) {
        $pdo = db();
        $ref = new_order_ref();
        try {
            $pdo->beginTransaction();
            $dec = $pdo->prepare('UPDATE vb_products SET stock = stock - ? WHERE id = ? AND active = 1 AND stock >= ?');
            foreach ($lines as $l) {
                $dec->execute([$l['qty'], (int) $l['product']['id'], $l['qty']]);
                if ($dec->rowCount() < 1) {
                    throw new RuntimeException($l['product']['name'] . ' just sold out. Update your cart and try again.');
                }
            }
            $ins = $pdo->prepare('INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, notes, subtotal, shipping, total, payment_method, payment_status, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([
                $ref, (int) $user['id'], $f['full_name'], $f['email'], $f['phone'], $f['address'], $f['city'], $f['region'],
                $f['notes'] !== '' ? $f['notes'] : null,
                $totals['subtotal'], $totals['shipping'], $totals['total'], $f['payment_method'], 'pending', 'pending',
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $item = $pdo->prepare('INSERT INTO vb_order_items (order_id, product_id, product_name, unit_price, quantity) VALUES (?, ?, ?, ?, ?)');
            foreach ($lines as $l) {
                $item->execute([$orderId, (int) $l['product']['id'], $l['product']['name'], (float) $l['product']['price'], $l['qty']]);
            }
            $pdo->commit();
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['stock'] = $ex->getMessage();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }

        if (!$errors) {
            cart_clear();
            $order = find_order_by_ref($ref);
            if ($f['payment_method'] === 'paystack') {
                start_payment($order);
            }
            flash('success', 'Order placed. You will pay when it arrives.');
            redirect('order.php?ref=' . urlencode($ref));
        }
    }
    $lines = cart_lines();
    $totals = cart_totals($lines);
}

$d = [
    'full_name' => val('full_name', $user['name']),
    'email' => val('email', $user['email']),
    'phone' => val('phone', (string) ($user['phone'] ?? '')),
    'address' => val('address'),
    'city' => val('city'),
    'region' => val('region', 'Greater Accra'),
    'notes' => val('notes'),
    'payment_method' => val('payment_method', 'paystack'),
];

$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section">
    <div class="page-head"><h1>Checkout</h1></div>
    <?php if ($errors): ?>
        <p class="flash flash-error" role="alert"><?= isset($errors['stock']) ? e($errors['stock']) : 'Fix the highlighted fields and place your order again.' ?></p>
    <?php endif; ?>

    <div class="cart-layout">
        <form method="post" action="<?= e(url('checkout.php')) ?>" class="form" novalidate>
            <?= csrf_field() ?>
            <h2>Delivery details</h2>
            <div class="form-grid">
                <?php
                $text = [
                    ['full_name', 'Full name', 'text', 'name', 'span-2'],
                    ['email', 'Email', 'email', 'email', ''],
                    ['phone', 'Phone number', 'tel', 'tel', ''],
                    ['address', 'Delivery address', 'text', 'street-address', 'span-2'],
                    ['city', 'Town or city', 'text', 'address-level2', ''],
                ];
                foreach ($text as [$key, $label, $type, $auto, $span]):
                ?>
                    <div class="field <?= e($span) ?><?= isset($errors[$key]) ? ' has-error' : '' ?>">
                        <label for="<?= e($key) ?>"><?= e($label) ?></label>
                        <input id="<?= e($key) ?>" type="<?= e($type) ?>" name="<?= e($key) ?>" value="<?= e($d[$key]) ?>" autocomplete="<?= e($auto) ?>" required>
                        <?php if (isset($errors[$key])): ?><p class="field-error"><?= e($errors[$key]) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="field<?= isset($errors['region']) ? ' has-error' : '' ?>">
                    <label for="region">Region</label>
                    <select id="region" name="region" required>
                        <?php foreach (ghana_regions() as $r): ?>
                            <option value="<?= e($r) ?>"<?= $d['region'] === $r ? ' selected' : '' ?>><?= e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['region'])): ?><p class="field-error"><?= e($errors['region']) ?></p><?php endif; ?>
                </div>
                <div class="field span-2<?= isset($errors['notes']) ? ' has-error' : '' ?>">
                    <label for="notes">Delivery notes (optional)</label>
                    <textarea id="notes" name="notes" rows="2" maxlength="500"><?= e($d['notes']) ?></textarea>
                    <?php if (isset($errors['notes'])): ?><p class="field-error"><?= e($errors['notes']) ?></p><?php endif; ?>
                </div>
            </div>

            <h2>Payment</h2>
            <fieldset class="choices<?= isset($errors['payment_method']) ? ' has-error' : '' ?>">
                <legend class="sr-only">Payment method</legend>
                <label class="choice">
                    <input type="radio" name="payment_method" value="paystack"<?= $d['payment_method'] === 'paystack' ? ' checked' : '' ?>>
                    <span class="choice-body">
                        <span class="choice-title">Card or mobile money</span>
                        <span class="choice-text">Visa, Mastercard, MTN MoMo, Telecel Cash and AT Money, through Paystack.</span>
                    </span>
                </label>
                <label class="choice">
                    <input type="radio" name="payment_method" value="cod"<?= $d['payment_method'] === 'cod' ? ' checked' : '' ?>>
                    <span class="choice-body">
                        <span class="choice-title">Pay on delivery</span>
                        <span class="choice-text">Pay the courier in cash or mobile money. Greater Accra and Ashanti only.</span>
                    </span>
                </label>
                <?php if (isset($errors['payment_method'])): ?><p class="field-error"><?= e($errors['payment_method']) ?></p><?php endif; ?>
            </fieldset>

            <button class="btn btn-primary btn-lg" type="submit">Place order</button>
            <p class="muted small">We never see or store your card or mobile money details. Paystack handles the payment on its own secure page.</p>
        </form>

        <aside class="summary" aria-label="Order summary">
            <h2>Your order</h2>
            <ul class="mini-lines">
                <?php foreach ($lines as $l): ?>
                    <li>
                        <img src="<?= e(asset($l['product']['image'])) ?>" alt="" width="800" height="800">
                        <span class="mini-name"><?= e($l['product']['name']) ?> <span class="muted">× <?= (int) $l['qty'] ?></span></span>
                        <span><?= e(money($l['line_total'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <dl class="sum-rows">
                <div><dt>Subtotal</dt><dd><?= e(money($totals['subtotal'])) ?></dd></div>
                <div><dt>Shipping</dt><dd><?= $totals['shipping'] > 0 ? e(money($totals['shipping'])) : 'Free' ?></dd></div>
                <div class="sum-total"><dt>Total</dt><dd><?= e(money($totals['total'])) ?></dd></div>
            </dl>
            <p><a class="text-link" href="<?= e(url('cart.php')) ?>">Edit cart</a></p>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
