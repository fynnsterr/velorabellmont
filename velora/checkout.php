<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$lines = cart_lines();
if (!$lines) {
    flash('info', 'Your cart is empty. Add a piece before checking out.');
    redirect('shop.php');
}
$promo = session_promo();
$redeem = $user ? min((int) ($_SESSION['redeem'] ?? 0), points_balance((int) $user['id'])) : 0;
$gift = session_gift();
$saved = $user ? user_addresses((int) $user['id']) : [];
$errors = [];
$codRegions = cod_regions();
$freeOver = (float) cfg('shipping.free_over', 500);

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
    if (!$errors && $f['payment_method'] !== 'cod' && !paystack_enabled() && cart_totals($lines, $f['region'], $promo, $redeem, $gift)['total'] > 0) {
        $errors['payment_method'] = 'Online payment is not set up yet. Choose pay on delivery if it is available in your region.';
    }
    if (!$errors && !throttle_hit('checkout:' . client_ip(), 12, 600)) {
        $errors['payment_method'] = 'Too many checkout attempts from this connection. Wait a few minutes and try again.';
    }
    if (!$errors && $promo && promo_used_by((string) $promo['code'], $user ? (int) $user['id'] : null, $f['email'])) {
        $errors['payment_method'] = 'The code ' . $promo['code'] . ' has already been used with this email. Remove it from your cart to continue.';
    }
    if (!$errors && $f['payment_method'] === 'cod') {
        $openCod = db()->prepare("SELECT COUNT(*) FROM vb_orders WHERE kind = 'shop' AND payment_method = 'cod' AND order_status = 'pending' AND (LOWER(email) = ? OR phone = ?)");
        $openCod->execute([$f['email'], $f['phone']]);
        if ((int) $openCod->fetchColumn() >= max(1, (int) cfg('orders.cod_open_limit', 2))) {
            $errors['payment_method'] = 'You already have pay on delivery orders waiting to be confirmed. Pay by mobile money or card, or wait until they are delivered.';
        }
    }

    $channels = ['momo' => 'mobile_money', 'card' => 'card', 'paystack' => null, 'cod' => null];
    if (!array_key_exists($f['payment_method'], $channels)) {
        $errors['payment_method'] = 'Choose how you want to pay.';
    } elseif ($f['payment_method'] === 'cod' && !in_array($f['region'], $codRegions, true)) {
        $errors['payment_method'] = 'Pay on delivery is only available in ' . cod_regions_text() . '. Pay by mobile money or card instead.';
    }

    if (!$errors) {
        $totals = cart_totals($lines, $f['region'], $promo, $redeem, $gift);
        $method = $totals['total'] <= 0 ? 'giftcard' : ($f['payment_method'] === 'cod' ? 'cod' : 'paystack');
        $channel = $method === 'paystack' ? $channels[$f['payment_method']] : null;
        $eta = delivery_estimate($f['region']);
        $pdo = db();
        $ref = new_order_ref();
        expire_unpaid_orders(10);
        try {
            $pdo->beginTransaction();
            $dec = $pdo->prepare('UPDATE vb_products SET stock = stock - ? WHERE id = ? AND active = 1 AND stock >= ?');
            $need = [];
            foreach ($lines as $l) {
                $need[(int) $l['product']['id']] = ($need[(int) $l['product']['id']] ?? 0) + $l['qty'];
            }
            foreach ($lines as $l) {
                $pid = (int) $l['product']['id'];
                if (!isset($need[$pid])) {
                    continue; 
                }
                $dec->execute([$need[$pid], $pid, $need[$pid]]);
                if ($dec->rowCount() < 1) {
                    throw new RuntimeException($l['product']['name'] . ' just sold out. Update your cart and try again.');
                }
                unset($need[$pid]);
            }
            if ($totals['promo_code'] === 'BIRTHDAY') {
                $bd = $pdo->prepare('UPDATE vb_users SET birthday_used_year = ? WHERE id = ? AND (birthday_used_year IS NULL OR birthday_used_year <> ?)');
                $bd->execute([(int) date('Y'), (int) $user['id'], (int) date('Y')]);
                if ($bd->rowCount() < 1) {
                    throw new RuntimeException('Your birthday reward was already used this year. Remove it from your cart and try again.');
                }
            } elseif ($totals['promo_code'] && $totals['promo_code'] !== 'BUNDLE') {
                $use = $pdo->prepare('UPDATE vb_promos SET used_count = used_count + 1 WHERE code = ? AND active = 1 AND (max_uses IS NULL OR used_count < max_uses)');
                $use->execute([$totals['promo_code']]);
                if ($use->rowCount() < 1) {
                    throw new RuntimeException('That promo code was just used up. Remove it from your cart and try again.');
                }
            }
            if ($totals['points_used'] > 0) {
                if (!$user || points_balance((int) $user['id']) < $totals['points_used']) {
                    throw new RuntimeException('Your points balance changed. Update your cart and try again.');
                }
                points_add((int) $user['id'], -$totals['points_used'], 'redeem:' . $ref);
            }
            if ($totals['gift_amount'] > 0) {
                $g = $pdo->prepare("UPDATE vb_gift_cards SET balance = balance - ? WHERE code = ? AND status = 'active' AND balance >= ?");
                $g->execute([$totals['gift_amount'], $totals['gift_code'], $totals['gift_amount']]);
                if ($g->rowCount() < 1) {
                    throw new RuntimeException('That gift card balance changed. Update your cart and try again.');
                }
            }
            $ins = $pdo->prepare('INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, notes, subtotal, discount, promo_code, points_used, points_discount, gift_code, gift_amount, shipping, total, delivery_estimate, payment_method, pay_channel, payment_status, order_status, whatsapp_optin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([
                $ref, $user ? (int) $user['id'] : null, $f['full_name'], $f['email'], $f['phone'], $f['address'], $f['city'], $f['region'],
                $f['notes'] !== '' ? $f['notes'] : null,
                $totals['subtotal'], $totals['discount'], $totals['promo_code'], $totals['points_used'], $totals['points_discount'], $totals['gift_code'], $totals['gift_amount'], $totals['shipping'], $totals['total'], $eta,
                $method, $channel, 'pending', 'pending', val('whatsapp_optin') === '1' ? 1 : 0,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $item = $pdo->prepare('INSERT INTO vb_order_items (order_id, product_id, product_name, size, unit_price, unit_cost, quantity) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($lines as $l) {
                $item->execute([$orderId, (int) $l['product']['id'], $l['product']['name'], $l['size'] !== '' ? $l['size'] : null, $l['unit'], $l['product']['cost_price'] ?? null, $l['qty']]);
            }
            $soldBy = [];
            foreach ($lines as $l) {
                $soldBy[(int) $l['product']['id']] = ($soldBy[(int) $l['product']['id']] ?? 0) + $l['qty'];
            }
            foreach ($soldBy as $spid => $sqty) {
                stock_log($spid, -$sqty, 'sale', $orderId);
            }
            order_event($orderId, 'placed');
            $pdo->commit();
            track_order_event('order_placed');
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
            remember_guest_order($ref);
            foreach (array_keys($soldBy) as $spid) {
                stock_alert_check($spid);
            }
            if (val('subscribe') === '1') {
                subscribe_email($f['email'], 'checkout');
            }
            $_SESSION['ship_region'] = $f['region'];
            if ($user && val('save_address') === '1') {
                $dup = db()->prepare('SELECT 1 FROM vb_addresses WHERE user_id = ? AND address = ? AND city = ?');
                $dup->execute([(int) $user['id'], $f['address'], $f['city']]);
                if (!$dup->fetchColumn()) {
                    address_save((int) $user['id'], ['label' => mb_substr(val('address_label') !== '' ? trim(val('address_label')) : 'Saved address', 0, 40), 'full_name' => $f['full_name'], 'phone' => $f['phone'], 'address' => $f['address'], 'city' => $f['city'], 'region' => $f['region']], false);
                }
            }
            $order = find_order_by_ref($ref);
            order_mail((int) $order['id'], 'order_confirmation');
            if ($method === 'giftcard') {
                mark_order_paid((int) $order['id'], 'GIFTCARD-' . $ref);
                flash('success', 'Order placed and paid with your gift card.');
                redirect('order.php?ref=' . urlencode($ref));
            }
            if ($method === 'paystack') {
                start_payment($order);
            }
            flash('success', 'Order placed. You will pay when it arrives.');
            redirect('order.php?ref=' . urlencode($ref));
        }
    }
    $lines = cart_lines();
}

$def = null;
foreach ($saved as $sa) {
    if ((int) $sa['is_default'] === 1) {
        $def = $sa;
    }
}
$d = [
    'full_name' => val('full_name', $def['full_name'] ?? ($user['name'] ?? '')),
    'email' => val('email', $user['email'] ?? ''),
    'phone' => val('phone', $def['phone'] ?? (string) ($user['phone'] ?? '')),
    'address' => val('address', $def['address'] ?? ''),
    'city' => val('city', $def['city'] ?? ''),
    'region' => val('region', $def['region'] ?? ($_SESSION['ship_region'] ?? 'Greater Accra')),
    'notes' => val('notes'),
    'payment_method' => val('payment_method', 'momo'),
];
if ($d['payment_method'] === 'paystack') {
    $d['payment_method'] = 'momo';
}
$totals = cart_totals($lines, $d['region'], $promo, $redeem, $gift);
$afterDiscount = $totals['after'];

$pageTitle = 'Checkout';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    track_event('checkout_start', '', true);
}
require __DIR__ . '/includes/header.php';
$step = 2;
require __DIR__ . '/includes/steps.php';
?>
<section class="wrap section">
<?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?><span hidden data-track="begin_checkout" data-value="<?= e(number_format((float) $afterDiscount, 2, '.', '')) ?>"></span><?php endif; ?>
    <div class="page-head">
        <h1>Checkout</h1>
        <?php if (!$user): ?>
            <p class="muted">Checking out as a guest. No account needed. <a class="text-link" href="<?= e(url('login.php?next=checkout.php')) ?>">Log in</a> if you have one.</p>
        <?php endif; ?>
    </div>
    <?php if ($errors): ?>
        <p class="flash flash-error" role="alert"><?= isset($errors['stock']) ? e($errors['stock']) : 'Fix the highlighted fields and place your order again.' ?></p>
    <?php endif; ?>

    <div class="cart-layout">
        <form method="post" action="<?= e(url('checkout.php')) ?>" class="form" novalidate>
            <?= csrf_field() ?>
            <h2>Delivery details</h2>
            <?php if ($saved): ?>
                <div class="field saved-pick">
                    <label for="saved">Use a saved address</label>
                    <select id="saved" data-saved-address>
                        <option value="">Enter a new address</option>
                        <?php foreach ($saved as $sa): ?>
                            <option value="<?= (int) $sa['id'] ?>" data-name="<?= e($sa['full_name']) ?>" data-phone="<?= e($sa['phone']) ?>" data-address="<?= e($sa['address']) ?>" data-city="<?= e($sa['city']) ?>" data-region="<?= e($sa['region']) ?>"><?= e($sa['label']) ?>: <?= e($sa['address']) ?>, <?= e($sa['city']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
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
                        <?php if ($key === 'email' && !$user): ?><p class="muted small">If you leave before paying, we will send one reminder to this address.</p><?php endif; ?>
                        <?php if (isset($errors[$key])): ?><p class="field-error"><?= e($errors[$key]) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="field<?= isset($errors['region']) ? ' has-error' : '' ?>">
                    <label for="region">Region</label>
                    <select id="region" name="region" required data-region-calc data-after="<?= e((string) $afterDiscount) ?>" data-gift="<?= e((string) ($gift ? $gift['balance'] : 0)) ?>" data-free-over="<?= e((string) $freeOver) ?>" data-cod="<?= e(implode(',', $codRegions)) ?>">
                        <?php foreach (ghana_regions() as $r): ?>
                            <option value="<?= e($r) ?>" data-fee="<?= e((string) delivery_zone($r)['fee']) ?>" data-eta="<?= e(delivery_estimate($r)) ?>"<?= $d['region'] === $r ? ' selected' : '' ?>><?= e($r) ?></option>
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

            <?php if ($user): ?>
                <div class="field span-2 save-addr">
                    <label class="check"><input type="checkbox" name="save_address" value="1"> Save this address to my address book</label>
                    <label class="sr-only" for="address_label">Name for this address</label>
                    <input id="address_label" type="text" name="address_label" maxlength="40" placeholder="Name it, for example Home" value="<?= e(val('address_label')) ?>">
                </div>
            <?php endif; ?>

            <div class="field span-2 save-addr">
                <label class="check"><input type="checkbox" name="subscribe" value="1"<?= val('subscribe') === '1' ? ' checked' : '' ?>> Email me news and offers (you can unsubscribe any time)</label>
            </div>

            <?php if (wa_enabled()): ?>
                <div class="field span-2 save-addr">
                    <label class="check"><input type="checkbox" name="whatsapp_optin" value="1"<?= val('whatsapp_optin') === '1' ? ' checked' : '' ?>> Send my order updates on WhatsApp, to the phone number above</label>
                </div>
            <?php endif; ?>

            <h2>Payment</h2>
            <?php require __DIR__ . '/includes/secure_note.php'; ?>
            <fieldset class="choices<?= isset($errors['payment_method']) ? ' has-error' : '' ?>">
                <legend class="sr-only">Payment method</legend>
                <label class="choice">
                    <input type="radio" name="payment_method" value="momo"<?= $d['payment_method'] === 'momo' ? ' checked' : '' ?>>
                    <span class="choice-body">
                        <span class="choice-title">Mobile money</span>
                        <span class="choice-text">MTN MoMo, Telecel Cash or AT Money. You approve the payment on your phone.</span>
                    </span>
                </label>
                <label class="choice">
                    <input type="radio" name="payment_method" value="card"<?= $d['payment_method'] === 'card' ? ' checked' : '' ?>>
                    <span class="choice-body">
                        <span class="choice-title">Card</span>
                        <span class="choice-text">Visa or Mastercard.</span>
                    </span>
                </label>
                <label class="choice" data-cod-choice>
                    <input type="radio" name="payment_method" value="cod"<?= $d['payment_method'] === 'cod' ? ' checked' : '' ?>>
                    <span class="choice-body">
                        <span class="choice-title">Pay on delivery</span>
                        <span class="choice-text">Pay the courier in cash or mobile money. <?= e(cod_regions_text()) ?> only.</span>
                    </span>
                </label>
                <?php if (isset($errors['payment_method'])): ?><p class="field-error"><?= e($errors['payment_method']) ?></p><?php endif; ?>
            </fieldset>

            <button class="btn btn-primary btn-lg" type="submit">Place order</button>
            <p class="muted small">We never see or store your card or mobile money details. Paystack handles mobile money and card payments on its own secure page.</p>
        </form>

        <aside class="summary" aria-label="Order summary">
            <h2>Your order</h2>
            <ul class="mini-lines">
                <?php foreach ($lines as $l): ?>
                    <li>
                        <?= img_html($l['product']['image'], '', ['width' => 800, 'height' => 800, 'sizes' => '72px']) ?>
                        <span class="mini-name"><?= e($l['product']['name']) ?><?= $l['size'] !== '' ? ' <span class="muted">(size ' . e($l['size']) . ')</span>' : '' ?> <span class="muted">× <?= (int) $l['qty'] ?></span></span>
                        <span><?= e(money($l['line_total'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <dl class="sum-rows" data-sub="<?= e((string) $afterDiscount) ?>">
                <div><dt>Subtotal</dt><dd><?= e(money($totals['subtotal'])) ?></dd></div>
                <?php if ($totals['discount'] > 0): ?>
                    <div><dt>Discount (<?= e((string) $totals['promo_code']) ?>)</dt><dd>-<?= e(money($totals['discount'])) ?></dd></div>
                <?php endif; ?>
                <?php if ($totals['points_used'] > 0): ?>
                    <div><dt>Points (<?= (int) $totals['points_used'] ?>)</dt><dd>-<?= e(money($totals['points_discount'])) ?></dd></div>
                <?php endif; ?>
                <div><dt>Delivery</dt><dd id="sum-ship"><?= $totals['shipping'] > 0 ? e(money($totals['shipping'])) : 'Free' ?></dd></div>
                <?php if ($gift): ?>
                    <div><dt>Gift card</dt><dd id="sum-gift">-<?= e(money($totals['gift_amount'])) ?></dd></div>
                <?php endif; ?>
                <div class="sum-total"><dt>Total</dt><dd id="sum-total"><?= e(money($totals['total'])) ?></dd></div>
            </dl>
            <?= free_delivery_bar((float) $afterDiscount, $freeOver) ?>
            <p class="eta"><strong>Estimated delivery</strong><br><span id="sum-eta"><?= e(delivery_estimate($d['region'])) ?></span></p>
            <p><a class="text-link" href="<?= e(url('cart.php')) ?>">Edit cart</a></p>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
