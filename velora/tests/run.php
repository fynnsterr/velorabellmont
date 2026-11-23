<?php
declare(strict_types=1);

ob_start();
$dbFile = tempnam(sys_get_temp_dir(), 'vbtest');
unlink($dbFile);
exec('python3 ' . escapeshellarg(__DIR__ . '/mkdb.py') . ' ' . escapeshellarg($dbFile) . ' 2>&1', $out, $rc);
if ($rc !== 0) {
    fwrite(STDERR, implode(PHP_EOL, $out) . PHP_EOL);
    exit(1);
}
putenv('VB_SQLITE=' . $dbFile);
session_save_path(sys_get_temp_dir());
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/tests/run.php';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
require dirname(__DIR__) . '/includes/bootstrap.php';
$GLOBALS['vb_config']['paystack']['public_key'] = '';
$GLOBALS['vb_config']['paystack']['secret_key'] = '';
$GLOBALS['vb_config']['mail']['enabled'] = false;

$pass = 0;
$fail = 0;

function check(string $name, bool $ok): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    fwrite(STDERR, ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL);
}



check('shipping is flat below the free threshold', shipping_cost(100.0) === 25.0);
check('shipping is free at the threshold', shipping_cost(500.0) === 0.0);
check('shipping is free above the threshold', shipping_cost(900.0) === 0.0);
check('shipping just under the threshold is charged', shipping_cost(499.99) === 25.0);
check('empty cart has no shipping', shipping_cost(0.0) === 0.0);

$totals = cart_totals([['line_total' => 180.0], ['line_total' => 145.5]]);
check('cart subtotal adds line totals', $totals['subtotal'] === 325.5);
check('cart total adds shipping', $totals['total'] === 350.5);
$free = cart_totals([['line_total' => 360.0], ['line_total' => 140.0]]);
check('cart total has no shipping when free', $free['total'] === 500.0 && $free['shipping'] === 0.0);

check('money formats with the cedi symbol', money(1234.5) === 'GH₵1,234.50');
check('paystack amount converts to pesewas', paystack_amount(505.0) === 50500);
check('paystack amount rounds floats safely', paystack_amount(19.99) === 1999);
check('paystack is off without a secret key', paystack_enabled() === false);

check('e() escapes html', e('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;');
check('e() escapes single quotes', e("a'b") === 'a&#039;b');

check('safe_next allows known pages', safe_next('checkout.php') === 'checkout.php');
check('safe_next blocks outside urls', safe_next('http://evil.example') === 'account.php');
check('safe_next blocks unknown paths', safe_next('../admin/index.php') === 'account.php');

$ref = new_order_ref();
check('order reference has the expected format', preg_match('/^VB-\d{6}-[0-9A-F]{6}$/', $ref) === 1);
check('order references are unique', new_order_ref() !== $ref);

check('there are 16 regions', count(ghana_regions()) === 16);
check('pay on delivery regions are listed', in_array('Greater Accra', ghana_regions(), true) && in_array('Ashanti', ghana_regions(), true));

$t1 = csrf_token();
check('csrf token is 64 hex characters', preg_match('/^[0-9a-f]{64}$/', $t1) === 1);
check('csrf token is stable within a session', csrf_token() === $t1);
check('csrf field contains the token', strpos(csrf_field(), $t1) !== false);

$_SESSION['cart'] = [];
cart_add(1, 2);
cart_add(1, 3);
check('cart_add accumulates quantity', cart_count() === 5);
cart_add(1, 100);
check('cart quantity is capped at 20', $_SESSION['cart'][1] === 20);
cart_set('1', 4);
check('cart_set replaces quantity', cart_count() === 4);
cart_set('1', 0);
check('cart_set to zero removes the line', cart_count() === 0);

check('badge escapes and capitalises', badge('paid') === '<span class="badge badge-paid">Paid</span>');

check('seed data has 15 products', (int) db()->query('SELECT COUNT(*) FROM vb_products')->fetchColumn() === 15);
check('seed data has an admin user', (int) db()->query("SELECT COUNT(*) FROM vb_users WHERE role = 'admin'")->fetchColumn() === 1);
check('admin password is hashed', password_verify('Velora@2026', (string) db()->query("SELECT password_hash FROM vb_users WHERE role = 'admin'")->fetchColumn()));

db()->exec('UPDATE vb_products SET stock = 3 WHERE id = 1');
$_SESSION['cart'] = [1 => 10];
$lines = cart_lines();
check('cart line quantity is limited to stock', count($lines) === 1 && $lines[0]['qty'] === 3);
db()->exec('UPDATE vb_products SET stock = 0 WHERE id = 1');
check('out of stock products drop out of the cart', cart_lines() === []);

db()->exec('UPDATE vb_products SET stock = 5 WHERE id = 2');
db()->exec("INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, subtotal, shipping, total, payment_method, payment_status, order_status) VALUES ('VB-TEST', 1, 'T', 't@e.com', '020', 'a', 'c', 'Ashanti', 100, 25, 125, 'paystack', 'pending', 'pending')");
$oid = (int) db()->lastInsertId();
db()->exec("INSERT INTO vb_order_items (order_id, product_id, product_name, unit_price, quantity) VALUES ($oid, 2, 'Perle Strand', 100, 2)");

mark_order_paid($oid, 'PS_REF_1');
$row = db()->query("SELECT payment_status, order_status, paystack_reference FROM vb_orders WHERE id = $oid")->fetch();
check('mark_order_paid sets paid and processing', $row['payment_status'] === 'paid' && $row['order_status'] === 'processing');
check('mark_order_paid stores the paystack reference', $row['paystack_reference'] === 'PS_REF_1');
mark_order_paid($oid, 'PS_REF_2');
$row = db()->query("SELECT paystack_reference FROM vb_orders WHERE id = $oid")->fetch();
check('mark_order_paid is idempotent', $row['paystack_reference'] === 'PS_REF_1');

restock_order($oid, 'cancel');
check('restocking returns the items to stock', (int) db()->query('SELECT stock FROM vb_products WHERE id = 2')->fetchColumn() === 7);

db()->exec("INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, subtotal, shipping, total, payment_method, payment_status, order_status, created_at) VALUES ('VB-OLD', NULL, 'T', 't@e.com', '020', 'a', 'c', 'Ashanti', 100, 25, 125, 'paystack', 'pending', 'pending', '2000-01-01 00:00:00')");
$old = (int) db()->lastInsertId();
db()->exec("INSERT INTO vb_order_items (order_id, product_id, product_name, unit_price, quantity) VALUES ($old, 2, 'Perle Strand', 100, 1)");
db()->exec("INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, subtotal, shipping, total, payment_method, payment_status, order_status) VALUES ('VB-NEW', NULL, 'T', 't@e.com', '020', 'a', 'c', 'Ashanti', 100, 25, 125, 'paystack', 'pending', 'pending')");
$new = (int) db()->lastInsertId();
$stockBefore = (int) db()->query('SELECT stock FROM vb_products WHERE id = 2')->fetchColumn();
check('expire_unpaid_orders cancels only old unpaid orders', expire_unpaid_orders() === 1);
check('expired order is cancelled', db()->query("SELECT order_status FROM vb_orders WHERE id = $old")->fetchColumn() === 'cancelled');
check('recent unpaid order is left alone', db()->query("SELECT order_status FROM vb_orders WHERE id = $new")->fetchColumn() === 'pending');
check('expired order returns its stock', (int) db()->query('SELECT stock FROM vb_products WHERE id = 2')->fetchColumn() === $stockBefore + 1);
mark_order_paid($old, 'LATE_REF');
$row = db()->query("SELECT payment_status, order_status FROM vb_orders WHERE id = $old")->fetch();
check('late payment keeps the order cancelled', $row['payment_status'] === 'paid' && $row['order_status'] === 'cancelled');
check('late payment is flagged for refund', (int) db()->query("SELECT COUNT(*) FROM vb_order_events WHERE order_id = $old AND event = 'paid_after_cancel'")->fetchColumn() === 1);

db()->exec("INSERT INTO vb_promos (code, kind, amount, active, used_count) VALUES ('TESTPROMO', 'percent', 10, 1, 3)");
db()->exec("INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, subtotal, shipping, total, payment_method, payment_status, order_status, promo_code) VALUES ('VB-PROMO', NULL, 'T', 't@e.com', '020', 'a', 'c', 'Ashanti', 100, 25, 125, 'paystack', 'pending', 'pending', 'TESTPROMO')");
$po = (int) db()->lastInsertId();
order_set_status($po, 'cancelled', ['notify' => false]);
check('cancelling an order gives its promo use back', (int) db()->query("SELECT used_count FROM vb_promos WHERE code = 'TESTPROMO'")->fetchColumn() === 2);

$GLOBALS['vb_config']['mail']['enabled'] = true;
db()->exec("INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, subtotal, shipping, total, payment_method, payment_status, order_status) VALUES ('VB-REM', NULL, 'Ama K', 'ama@e.com', '020', 'a', 'c', 'Ashanti', 100, 25, 125, 'paystack', 'pending', 'pending')");
$rem = (int) db()->lastInsertId();
$GLOBALS['vb_config']['mail']['brevo_key'] = '';
$GLOBALS['vb_config']['mail']['enabled'] = false;
check('payment reminder builds for an unpaid order', order_mail($rem, 'payment_reminder') === false && (int) db()->query("SELECT COUNT(*) FROM vb_mail_log WHERE order_id = $rem AND mail_type = 'payment_reminder'")->fetchColumn() === 1);
check('payment reminder is sent only once per order', order_mail($rem, 'payment_reminder') === false && (int) db()->query("SELECT COUNT(*) FROM vb_mail_log WHERE order_id = $rem AND mail_type = 'payment_reminder'")->fetchColumn() === 1);
db()->exec("INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, subtotal, shipping, total, payment_method, payment_status, order_status, created_at) VALUES ('VB-STALE', NULL, 'Old', 'old@e.com', '020', 'a', 'c', 'Ashanti', 100, 25, 125, 'paystack', 'pending', 'pending', '" . days_ago(5) . "')");
$stale = (int) db()->lastInsertId();
check('no payment reminder for an order past its unpaid window', order_mail($stale, 'payment_reminder') === false && (int) db()->query("SELECT COUNT(*) FROM vb_mail_log WHERE order_id = $stale")->fetchColumn() === 0);
mark_order_paid($rem, 'REM_REF');
db()->exec("DELETE FROM vb_mail_log WHERE order_id = $rem");
check('no payment reminder for a paid order', order_mail($rem, 'payment_reminder') === false && (int) db()->query("SELECT COUNT(*) FROM vb_mail_log WHERE order_id = $rem AND mail_type = 'payment_reminder'")->fetchColumn() === 0);

check('free delivery bar shows the amount left', strpos(free_delivery_bar(200.0, 500.0), 'GH₵300.00') !== false && strpos(free_delivery_bar(200.0, 500.0), 'aria-valuenow="40"') !== false);
check('free delivery bar shows unlocked state', strpos(free_delivery_bar(500.0, 500.0), 'unlocked') !== false && strpos(free_delivery_bar(900.0, 500.0), 'aria-valuenow="100"') !== false);
$GLOBALS['vb_config']['site']['support_email'] = 'shop@gmail.com';
$GLOBALS['vb_config']['mail']['from'] = '';
$GLOBALS['vb_config']['mail']['from'] = 'orders@velorabellmont.com';

function mk_user(string $name, string $email, string $phone, ?int $by = null, bool $verified = true): int
{
    db()->prepare('INSERT INTO vb_users (name, email, phone, password_hash, role, referred_by, email_verified_at, verify_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $email, $phone, 'x', 'customer', $by, $verified ? date('Y-m-d H:i:s') : null, $verified ? null : bin2hex(random_bytes(16))]);
    return (int) db()->lastInsertId();
}

function mk_order(int $uid, string $ref, string $phone, string $address, string $status = 'delivered'): int
{
    db()->prepare("INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, subtotal, shipping, total, payment_method, payment_status, order_status) VALUES (?, ?, 'T', 't@e.com', ?, ?, 'Accra', 'Greater Accra', 100, 25, 125, 'paystack', 'paid', ?)")
        ->execute([$ref, $uid, $phone, $address, $status]);
    return (int) db()->lastInsertId();
}

function ref_pts(int $uid): int
{
    return (int) db()->query("SELECT COALESCE(SUM(points), 0) FROM vb_points WHERE user_id = $uid AND reason LIKE 'referral:%'")->fetchColumn();
}

$GLOBALS['vb_config']['mail']['enabled'] = false;
$boss = mk_user('Boss Ref', 'boss@e.com', '024 111 2222');
$fan = mk_user('New Fan', 'fan@e.com', '055 333 4444', $boss, false);
$fanOrder = mk_order($fan, 'VB-R1', '055 333 4444', '12 Palm Street');
check('no referral points while the referred email is unverified', award_referral_points($fanOrder) === false && ref_pts($boss) === 0);
$tok = (string) db()->query("SELECT verify_token FROM vb_users WHERE id = $fan")->fetchColumn();
check('a bad verification token is rejected', verification_confirm('abc') === null);
check('verification confirms the email', verification_confirm($tok) !== null && (string) db()->query("SELECT email_verified_at FROM vb_users WHERE id = $fan")->fetchColumn() !== '');
check('verifying releases referral points for a delivered first order', ref_pts($boss) === (int) loyalty('referral_points'));
check('a verification token works only once', verification_confirm($tok) === null);
check('referral points are awarded only once', award_referral_points($fanOrder) === false && ref_pts($boss) === (int) loyalty('referral_points'));

$pending = mk_user('Late Fan', 'late@e.com', '050 000 1111', $boss);
$pendingOrder = mk_order($pending, 'VB-R2', '050 000 1111', '5 River Road', 'processing');
check('no referral points before delivery', award_referral_points($pendingOrder) === false);
db()->exec("UPDATE vb_orders SET order_status = 'delivered' WHERE id = $pendingOrder");
check('referral points arrive once the order is delivered', award_referral_points($pendingOrder) === true && ref_pts($boss) === 2 * (int) loyalty('referral_points'));

$twin = mk_user('Twin', 'twin@e.com', '024 111 2222', $boss);
check('same phone as the referrer is blocked', award_referral_points(mk_order($twin, 'VB-R3', '024 111 2222', '9 Other Lane')) === false);
$bossOrder = mk_order($boss, 'VB-B1', '024 111 2222', '77 Boss Avenue');
$roommate = mk_user('Roommate', 'room@e.com', '027 555 6666', $boss);
check('same delivery address as the referrer is blocked', award_referral_points(mk_order($roommate, 'VB-R4', '027 555 6666', '77 boss avenue')) === false);

$GLOBALS['vb_config']['loyalty']['referral_monthly_cap'] = 2;
$extra = mk_user('Extra', 'extra@e.com', '020 777 8888', $boss);
check('monthly referral cap is enforced', award_referral_points(mk_order($extra, 'VB-R5', '020 777 8888', '3 Cap Close')) === false);
unset($GLOBALS['vb_config']['loyalty']['referral_monthly_cap']);

$before = ref_pts($boss);
$fanOrderRow = order_by_id($fanOrder);
reverse_order_rewards($fanOrderRow);
check('a full refund takes the referral points back', (int) db()->query("SELECT COALESCE(SUM(points), 0) FROM vb_points WHERE user_id = $boss AND reason = 'reverse-referral:$fan'")->fetchColumn() === -(int) loyalty('referral_points'));

$unv = mk_user('Unverified', 'unv@e.com', '026 000 9999', null, false);
db()->exec("UPDATE vb_users SET verify_token = NULL WHERE id = $unv");
check('verification mail builds a token for an unverified user', verification_send($unv) === false && preg_match('/^[0-9a-f]{32}$/', (string) db()->query("SELECT verify_token FROM vb_users WHERE id = $unv")->fetchColumn()) === 1);
check('no verification mail for a verified user', verification_send($boss) === false && (int) db()->query("SELECT COUNT(*) FROM vb_mail_log WHERE mail_type = 'verify_email' AND recipient = 'boss@e.com'")->fetchColumn() === 0);

db()->exec("UPDATE vb_products SET stock = 0 WHERE id = 3");
check('alert rejects a bad email', stock_alert_subscribe(3, 'nope') === 'invalid');
check('no alert when the piece is in stock', stock_alert_subscribe(2, 'wait@e.com') === 'in_stock');
check('alert is saved for a sold-out piece', stock_alert_subscribe(3, 'Wait@E.com') === 'added');
check('alert is not duplicated', stock_alert_subscribe(3, 'wait@e.com') === 'exists');
$mails = [];
$spy = function ($to, $subject, $text, $opt) use (&$mails) { $mails[] = [$to, $subject]; return true; };
check('nothing is sent while still sold out', stock_alerts_send(3, $spy) === 0 && !$mails);
db()->exec("UPDATE vb_products SET stock = 4 WHERE id = 3");
check('alert is sent when the piece is back', stock_alerts_send(3, $spy) === 1 && $mails[0][0] === 'wait@e.com');
check('alert is sent only once', stock_alerts_send(3, $spy) === 0 && count($mails) === 1);
db()->exec("UPDATE vb_products SET stock = 0 WHERE id = 3");
stock_alert_subscribe(3, 'again@e.com');
db()->exec("UPDATE vb_products SET stock = 4 WHERE id = 3");

function days_ago(int $d): string
{
    return date('Y-m-d H:i:s', time() - $d * 86400);
}

function mail_rows(string $type, string $to = ''): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM vb_mail_log WHERE mail_type = ? AND (? = '' OR recipient = ?)");
    $st->execute([$type, $to, $to]);
    return (int) $st->fetchColumn();
}

$GLOBALS['vb_config']['mail']['enabled'] = false;
$mails = [];
$spy = function ($to, $subject, $text, $opt) use (&$mails) { $mails[] = ['to' => $to, 'subject' => $subject, 'text' => $text, 'opt' => $opt]; return true; };

$wb = mk_user('Win Back', 'wb@e.com', '050 123 4567');
subscribe_email('wb@e.com', 'test');
$wbOrder = mk_order($wb, 'VB-W1', '050 123 4567', '1 Back Street', 'delivered');
db()->exec("UPDATE vb_orders SET email = 'wb@e.com', created_at = '" . days_ago(35) . "' WHERE id = $wbOrder");
check('win-back sends at 30 days to a subscriber', lifecycle_send_winback($spy) === 1 && $mails[0]['to'] === 'wb@e.com' && strpos($mails[0]['text'], 'new') !== false);
check('win-back includes an unsubscribe link', !empty($mails[0]['opt']['unsubscribe_url']));
check('win-back is not repeated for the same stage', lifecycle_send_winback($spy) === 0);
db()->exec("UPDATE vb_orders SET created_at = '" . days_ago(65) . "' WHERE id = $wbOrder");
check('win-back sends the next stage at 60 days', lifecycle_send_winback($spy) === 1);

$mails = [];
$quiet = mk_user('Quiet', 'quiet@e.com', '050 765 4321');
$quietOrder = mk_order($quiet, 'VB-W2', '050 765 4321', '2 Quiet Road', 'delivered');
db()->exec("UPDATE vb_orders SET email = 'quiet@e.com', created_at = '" . days_ago(35) . "' WHERE id = $quietOrder");
check('win-back skips people who did not opt in', lifecycle_send_winback($spy) === 0 && !$mails);
subscribe_email('quiet@e.com', 'test');
unsubscribe_email('quiet@e.com');
check('win-back skips people who unsubscribed', lifecycle_send_winback($spy) === 0 && !$mails);
subscribe_email('quiet@e.com', 'test');
$again = mk_order($quiet, 'VB-W3', '050 765 4321', '2 Quiet Road', 'processing');
db()->exec("UPDATE vb_orders SET email = 'quiet@e.com', payment_status = 'pending' WHERE id = $again");
check('win-back skips people who ordered again since', lifecycle_send_winback($spy) === 0 && !$mails);

$wl = mk_user('Wish Lister', 'wl@e.com', '050 888 9999');
subscribe_email('wl@e.com', 'test');
db()->exec("UPDATE vb_products SET stock = 3 WHERE id = 4");
db()->exec("INSERT INTO vb_wishlist (user_id, product_id, created_at) VALUES ($wl, 4, '" . days_ago(4) . "')");
$mails = [];
check('wishlist reminder is sent for a saved piece in stock', lifecycle_send_wishlist($spy) === 1 && $mails[0]['to'] === 'wl@e.com' && strpos($mails[0]['text'], 'running low') !== false);
check('wishlist reminder is sent once a month', lifecycle_send_wishlist($spy) === 0);
$wl2 = mk_user('Fresh Lister', 'wl2@e.com', '050 888 0000');
subscribe_email('wl2@e.com', 'test');
db()->exec("INSERT INTO vb_wishlist (user_id, product_id, created_at) VALUES ($wl2, 4, CURRENT_TIMESTAMP)");
check('wishlist reminder waits three days', lifecycle_send_wishlist($spy) === 0);
$wl3 = mk_user('Bought It', 'wl3@e.com', '050 888 1111');
subscribe_email('wl3@e.com', 'test');
db()->exec("INSERT INTO vb_wishlist (user_id, product_id, created_at) VALUES ($wl3, 4, '" . days_ago(4) . "')");
$boughtOrder = mk_order($wl3, 'VB-W4', '050 888 1111', '3 Bought Way', 'delivered');
db()->exec("INSERT INTO vb_order_items (order_id, product_id, product_name, unit_price, quantity) VALUES ($boughtOrder, 4, 'Bought', 100, 1)");
check('wishlist reminder skips pieces already bought', lifecycle_send_wishlist($spy) === 0);

$care = mk_user('Care Fan', 'care@e.com', '050 222 3333');
$careOrder = mk_order($care, 'VB-C1', '050 222 3333', '4 Care Close', 'delivered');
db()->exec("UPDATE vb_orders SET email = 'care@e.com', delivered_at = '" . days_ago(2) . "' WHERE id = $careOrder");
db()->exec("INSERT INTO vb_order_items (order_id, product_id, product_name, unit_price, quantity) VALUES ($careOrder, 2, 'Perle Strand', 100, 1)");
$oldOrder = mk_order($care, 'VB-C2', '050 222 3333', '4 Care Close', 'delivered');
db()->exec("UPDATE vb_orders SET email = 'care@e.com', delivered_at = '" . days_ago(40) . "' WHERE id = $oldOrder");
lifecycle_send_care_tips();
check('care tips go out one day after delivery', mail_rows('care_tips', 'care@e.com') === 1);
check('care tips skip old deliveries', (int) db()->query("SELECT COUNT(*) FROM vb_mail_log WHERE mail_type = 'care_tips' AND order_id = $oldOrder")->fetchColumn() === 0);
lifecycle_send_care_tips();
check('care tips are sent once per order', mail_rows('care_tips', 'care@e.com') === 1);
check('care tips list real care advice', count(care_tip_lines()) === 6);

db()->exec("UPDATE vb_orders SET delivered_at = '" . days_ago(9) . "' WHERE id = $careOrder");
lifecycle_send_complete_set();
check('complete the set skips people who did not opt in', mail_rows('complete_set', 'care@e.com') === 0);
subscribe_email('care@e.com', 'test');
$matches = complete_set_for_order($careOrder);
check('complete the set suggests pieces not already in the order', count($matches) > 0 && !in_array(2, array_map(fn ($m) => (int) $m['id'], $matches), true));
lifecycle_send_complete_set();
check('complete the set is sent to a subscriber after a week', mail_rows('complete_set', 'care@e.com') === 1);

$mkLine = fn (int $cat, float $total) => ['product' => ['category_id' => $cat], 'line_total' => $total];
$two = [$mkLine(1, 100.0), $mkLine(2, 100.0)];
$t = cart_totals($two, 'Greater Accra', null);
check('bundle saves 10% across two categories', $t['discount'] === 20.0 && $t['promo_code'] === 'BUNDLE' && $t['after'] === 180.0);
$t = cart_totals([$mkLine(1, 100.0), $mkLine(1, 100.0)], 'Greater Accra', null);
check('no bundle saving within one category', $t['discount'] === 0.0 && $t['promo_code'] === null);
$promoRow = ['code' => 'FIVE', 'active' => 1, 'kind' => 'percent', 'amount' => 5, 'min_subtotal' => 0, 'expires_on' => null, 'max_uses' => null, 'used_count' => 0];
$t = cart_totals($two, 'Greater Accra', $promoRow);
check('a promo code replaces the bundle saving', $t['discount'] === 10.0 && $t['promo_code'] === 'FIVE');
$GLOBALS['vb_config']['bundle']['percent'] = 0;
check('bundle saving can be switched off', cart_totals($two, 'Greater Accra', null)['discount'] === 0.0);
unset($GLOBALS['vb_config']['bundle']);

check('gifts under 200 are all within budget', count(gift_products_under(200.0)) > 0 && count(array_filter(gift_products_under(200.0), fn ($p) => price_now($p) > 200)) === 0);
check('gift finder knows the church occasion and the 200 budget', isset(gift_occasions()['church']) && isset(gift_budgets()['under_200']) && count(gift_finder('mum', 'church', 'under_200', 'any')) > 0);
check('wedding and church collections are seeded', (int) db()->query("SELECT COUNT(*) FROM vb_collections WHERE slug IN ('wedding-guest', 'church-sunday')")->fetchColumn() === 2 && (int) db()->query("SELECT COUNT(*) FROM vb_collection_items ci JOIN vb_collections c ON c.id = ci.collection_id WHERE c.slug = 'church-sunday'")->fetchColumn() === 5);

check('reminder rejects a short name', gift_date_add($boss, 'x', 'mum', 'birthday', 12, 25) !== null);
check('reminder rejects an impossible date', gift_date_add($boss, 'Mum', 'mum', 'birthday', 2, 30) !== null);
check('reminder rejects an unknown occasion', gift_date_add($boss, 'Mum', 'mum', 'nope', 12, 25) !== null);
check('reminder is saved', gift_date_add($boss, 'Mum', 'mum', 'birthday', 12, 25) === null && count(gift_dates_for_user($boss)) === 1);
check('next date is this year when still ahead', gift_next_date(12, 25, strtotime('2026-12-18')) === ['2026-12-25', 7]);
check('next date rolls to next year when passed', gift_next_date(1, 5, strtotime('2026-11-19')) === ['2027-01-05', 47]);
check('29 February falls back to the 28th in a normal year', gift_next_date(2, 29, strtotime('2027-02-20'))[0] === '2027-02-28');
$mails = [];
check('no reminder more than a week ahead', lifecycle_send_gift_reminders($spy, 7, strtotime('2026-12-10')) === 0);
check('reminder is emailed a week before', lifecycle_send_gift_reminders($spy, 7, strtotime('2026-12-18')) === 1 && $mails[0]['to'] === 'boss@e.com' && strpos($mails[0]['text'], 'giftfinder.php?who=mum&occasion=birthday') !== false);
check('reminder is sent once per year', lifecycle_send_gift_reminders($spy, 7, strtotime('2026-12-19')) === 0);
$rid = (int) db()->query("SELECT id FROM vb_gift_dates WHERE user_id = $boss")->fetchColumn();
gift_date_delete($fan, $rid);
check('another user cannot delete a reminder', count(gift_dates_for_user($boss)) === 1);
gift_date_delete($boss, $rid);
check('owner can delete a reminder', count(gift_dates_for_user($boss)) === 0);

$bday = mk_user('Birthday Girl', 'bday@e.com', '050 999 0000');
subscribe_email('bday@e.com', 'test');
db()->prepare('UPDATE vb_users SET birthday = ? WHERE id = ?')->execute([date('Y') - 25 . '-' . date('m-d'), $bday]);
$mails = [];
check('birthday email goes to a subscriber in their birthday month', lifecycle_send_birthday($spy) === 1 && $mails[0]['to'] === 'bday@e.com');
check('birthday email is sent once a year', lifecycle_send_birthday($spy) === 0);

check('ghana numbers become international', wa_phone_e164('024 123 4567') === '233241234567' && wa_phone_e164('+233 24 123 4567') === '233241234567' && wa_phone_e164('241234567') === '233241234567');
check('foreign numbers are kept and junk is rejected', wa_phone_e164('+44 7700 900123') === '447700900123' && wa_phone_e164('12') === '');
$waUser = mk_user('Kofi Mensah', 'kofi@e.com', '024 123 4567');
$waOrder = mk_order($waUser, 'VB-WA1', '024 123 4567', '8 Chat Lane', 'processing');
db()->exec("UPDATE vb_orders SET whatsapp_optin = 1, courier = 'Swift Riders', full_name = 'Kofi Mensah' WHERE id = $waOrder");
$calls = [];
$okTransport = function ($url, $headers, $payload) use (&$calls) { $calls[] = ['url' => $url, 'headers' => $headers, 'payload' => $payload]; return [200, '{}']; };
check('whatsapp does nothing until it is configured', wa_order_event($waOrder, 'confirmed', $okTransport) === false && !$calls);
$GLOBALS['vb_config']['whatsapp'] = ['token' => 'tok123', 'phone_number_id' => '555'];
check('whatsapp sends a template to the customer', wa_order_event($waOrder, 'confirmed', $okTransport) === true && $calls[0]['payload']['to'] === '233241234567' && $calls[0]['payload']['template']['name'] === 'velora_order_confirmed' && strpos($calls[0]['url'], '/555/messages') !== false);
$texts = array_column($calls[0]['payload']['template']['components'][0]['parameters'], 'text');
check('whatsapp passes name, reference and total', $texts === ['Kofi', 'VB-WA1', 'GH₵125.00'] && in_array('Authorization: Bearer tok123', $calls[0]['headers'], true));
check('whatsapp event is sent only once', wa_order_event($waOrder, 'confirmed', $okTransport) === false && count($calls) === 1);
check('dispatch message names the courier', wa_order_event($waOrder, 'dispatched', $okTransport) === true && end($calls)['payload']['template']['components'][0]['parameters'][2]['text'] === 'Swift Riders');
check('no payment reminder on a paid order', wa_order_event($waOrder, 'payment_reminder', $okTransport) === false);
$failTransport = fn () => [400, '{"error":{"message":"Template not found"}}'];
check('a failed send is logged with the reason', wa_order_event($waOrder, 'delivered', $failTransport) === false && strpos((string) db()->query("SELECT detail FROM vb_wa_log WHERE order_id = $waOrder AND event = 'delivered'")->fetchColumn(), 'Template not found') !== false);
check('a failed send is retried', wa_order_event($waOrder, 'delivered', $okTransport) === true && (int) db()->query("SELECT attempts FROM vb_wa_log WHERE order_id = $waOrder AND event = 'delivered'")->fetchColumn() === 2);
$plain = mk_order($waUser, 'VB-WA2', '024 123 4567', '8 Chat Lane', 'processing');
$before = count($calls);
check('whatsapp skips customers who did not opt in', wa_order_event($plain, 'confirmed', $okTransport) === false && count($calls) === $before);
unset($GLOBALS['vb_config']['whatsapp']);

check('admin can open every page', staff_can('admin', 'staff.php') && staff_can('admin', 'refunds.php'));
check('fulfilment can open orders but not staff', staff_can('fulfilment', 'orders.php') && staff_can('fulfilment', 'packing.php') && !staff_can('fulfilment', 'staff.php') && !staff_can('fulfilment', 'analytics.php'));
check('support cannot open refunds or bulk tools', !staff_can('support', 'refunds.php') && !staff_can('support', 'bulk.php') && staff_can('support', 'customers.php'));
check('marketing can open campaigns but not orders', staff_can('marketing', 'campaigns.php') && !staff_can('marketing', 'orders.php'));
check('unknown roles can open nothing', !staff_can('customer', 'index.php') && !is_staff(['role' => 'customer']) && is_staff(['role' => 'support']));
check('only admin can refund or confirm payment', staff_can_action('admin', 'refund_create') && !staff_can_action('fulfilment', 'refund_create') && !staff_can_action('fulfilment', 'pay') && staff_can_action('fulfilment', 'status') && !staff_can_action('support', 'status'));

$boss2 = mk_user('Owner', 'owner@e.com', '020 000 0000');
$pack = mk_user('Packer', 'packer@e.com', '020 111 1111');
check('role cannot be given to an unknown email', staff_set_role('nobody@e.com', 'support', $boss2)[0] === false);
check('an invalid role is refused', staff_set_role('packer@e.com', 'god', $boss2)[0] === false);
check('admin can give a role', staff_set_role('owner@e.com', 'admin', $boss2)[0] === true && staff_set_role('packer@e.com', 'fulfilment', $boss2)[0] === true);
$seed = (string) db()->query("SELECT email FROM vb_users WHERE role = 'admin' AND email <> 'owner@e.com' LIMIT 1")->fetchColumn();
if ($seed !== '') {
    staff_set_role($seed, 'customer', $boss2);
}
check('the last admin cannot be removed', (int) db()->query("SELECT COUNT(*) FROM vb_users WHERE role = 'admin'")->fetchColumn() === 1 && staff_set_role('owner@e.com', 'customer', $pack)[0] === false);
check('admins cannot demote themselves', staff_set_role('owner@e.com', 'support', $boss2)[0] === false);
check('role changes are written to the activity log', (int) db()->query("SELECT COUNT(*) FROM vb_activity WHERE action = 'staff_role'")->fetchColumn() >= 2);
log_activity('test', 'orders', 'hello', ['id' => $boss2, 'name' => 'Owner', 'role' => 'admin']);
check('activity log shows who did what', activity_recent(5)[0]['user_name'] === 'Owner' && activity_recent(5, $boss2)[0]['action'] === 'test' && count(activity_recent(5, $pack)) === 0);


$ownerOrder = mk_order($boss2, 'VB-OWN1', '020 000 0000', '1 Owner St', 'processing');
db()->exec("INSERT INTO vb_order_items (order_id, product_id, product_name, size, unit_price, quantity) VALUES ($ownerOrder, NULL, 'Orla Hoops', '7', 62.50, 2)");
$GLOBALS['vb_config']['site']['support_email'] = 'shop@e.com';
$sent = [];
$spy2 = function ($to, $subject, $text, $opt = []) use (&$sent) { $sent[] = compact('to', 'subject', 'text'); return true; };
check('owner gets an email for a new order', owner_new_order_alert($ownerOrder, $spy2, null) === true && $sent[0]['to'] === 'shop@e.com' && strpos($sent[0]['subject'], 'VB-OWN1') !== false);
check('alert lists items, address and links', strpos($sent[0]['text'], '2 x Orla Hoops (size 7)') !== false && strpos($sent[0]['text'], '1 Owner St') !== false && strpos($sent[0]['text'], 'admin/packing.php?id=' . $ownerOrder) !== false);
check('owner is alerted once per order', owner_new_order_alert($ownerOrder, $spy2, null) === false && count($sent) === 1);
$GLOBALS['vb_config']['alerts'] = ['order_email' => 'boss@shop.com'];
$o2 = mk_order($boss2, 'VB-OWN2', '020 000 0000', '2 Owner St', 'processing');
$sent = [];
check('alerts.order_email overrides the default address', owner_new_order_alert($o2, $spy2, null) === true && $sent[0]['to'] === 'boss@shop.com');
$failSender = fn () => false;
$o3 = mk_order($boss2, 'VB-OWN3', '020 000 0000', '3 Owner St', 'processing');
check('a failed alert can be retried', owner_new_order_alert($o3, $failSender, null) === false && owner_new_order_alert($o3, $spy2, null) === true);
$waCalls = [];
$waOk = function ($url, $headers, $payload) use (&$waCalls) { $waCalls[] = $payload; return [200, '{}']; };
$GLOBALS['vb_config']['whatsapp'] = ['token' => 'tok', 'phone_number_id' => '555'];
$o4 = mk_order($boss2, 'VB-OWN4', '020 000 0000', '4 Owner St', 'processing');
check('owner whatsapp is skipped without a number', owner_new_order_alert($o4, $spy2, $waOk) === true && !$waCalls);
$GLOBALS['vb_config']['alerts']['whatsapp_to'] = '024 555 0000';
$o5 = mk_order($boss2, 'VB-OWN5', '020 000 0000', '5 Owner St', 'processing');
check('owner gets a whatsapp template for a new order', owner_new_order_alert($o5, $spy2, $waOk) === true && $waCalls[0]['to'] === '233245550000' && $waCalls[0]['template']['name'] === 'velora_new_order_owner' && $waCalls[0]['template']['components'][0]['parameters'][0]['text'] === 'VB-OWN5');
unset($GLOBALS['vb_config']['whatsapp'], $GLOBALS['vb_config']['alerts']);

$pr = db()->query('SELECT id, slug, name, price, stock, category_id FROM vb_products ORDER BY id LIMIT 1')->fetch();
$cat1 = (int) $pr['category_id'];
$catName = (string) db()->query("SELECT name FROM vb_categories WHERE id = $cat1")->fetchColumn();
$csv = bulk_export_csv();
check('export has every column and a product row', strpos($csv, 'slug,name,category,price,cost_price,stock,low_stock_at,active') !== false && strpos($csv, $pr['slug']) !== false);
[$rows, $err] = bulk_parse($csv);
check('an exported file re-imports with nothing to change', $err === null && count(array_filter(bulk_plan($rows), fn ($p) => $p['type'] !== 'same')) === 0);
[$none, $e2] = bulk_parse("foo,bar\n1,2\n");
check('a file without slug or name is refused', $none === [] && $e2 !== null);
$newPrice = (float) $pr['price'] + 10;
$newStock = (int) $pr['stock'] + 5;
$sheet = "slug,name,category,price,cost_price,stock,active\n"
    . $pr['slug'] . ",,," . $newPrice . ",12.5," . $newStock . ",\n"
    . "ghost-item,,,10,,,\n"
    . "," . "Brand New Hoops," . $catName . ",88,40,3,\n"
    . "," . "Bad Price Ring," . $catName . ",abc,,,\n"
    . $pr['slug'] . ",,,50,,,\n";
[$rows, $err] = bulk_parse($sheet);
$plan = bulk_plan($rows);
check('plan finds one update, one new product and three problems', array_count_values(array_column($plan, 'type')) === ['update' => 1, 'error' => 3, 'create' => 1]);
check('plan shows the old and new price', $plan[0]['changes']['price'][1] === $newPrice && abs((float) $plan[0]['changes']['price'][0] - (float) $pr['price']) < 0.01);
check('a repeated slug is flagged', strpos($plan[4]['msg'], 'twice') !== false);
$res = bulk_apply($plan, $boss2);
$after = db()->query('SELECT price, stock, cost_price FROM vb_products WHERE id = ' . (int) $pr['id'])->fetch();
check('import updates price, cost and stock', abs((float) $after['price'] - $newPrice) < 0.01 && (int) $after['stock'] === $newStock && abs((float) $after['cost_price'] - 12.5) < 0.01 && $res['updated'] === 1);
check('stock changes from an import are logged as stock moves', (int) db()->query("SELECT COUNT(*) FROM vb_stock_moves WHERE product_id = " . (int) $pr['id'] . " AND note = 'Bulk CSV import'")->fetchColumn() === 1);
$made = db()->query("SELECT * FROM vb_products WHERE name = 'Brand New Hoops'")->fetch();
check('a new product from the file is added hidden with a unique slug', $made && (int) $made['active'] === 0 && $made['slug'] === 'brand-new-hoops' && (int) $made['stock'] === 3 && $res['created'] === 1);
[$pct, $pmsg, $pn] = bulk_price_percent($cat1, 10.0, $boss2);
$check = db()->query('SELECT price FROM vb_products WHERE id = ' . (int) $pr['id'])->fetchColumn();
check('percent change raises prices in a category', $pct === true && $pn > 0 && abs((float) $check - round($newPrice * 1.1, 2)) < 0.02);
check('percent change refuses silly values', bulk_price_percent(null, 0.0, $boss2)[0] === false && bulk_price_percent(null, -95.0, $boss2)[0] === false && bulk_price_percent(null, 500.0, $boss2)[0] === false);
[$changed, $errs] = bulk_grid_save([(int) $pr['id'] => '77.00'], [(int) $pr['id'] => '9'], $boss2);
$g = db()->query('SELECT price, stock FROM vb_products WHERE id = ' . (int) $pr['id'])->fetch();
check('grid edit saves price and stock', $changed === 2 && $errs === [] && abs((float) $g['price'] - 77.0) < 0.01 && (int) $g['stock'] === 9);
[$c2, $e3] = bulk_grid_save([(int) $pr['id'] => 'free'], [(int) $pr['id'] => '-4'], $boss2);
check('grid edit rejects bad numbers', $c2 === 0 && count($e3) === 2);

$packed = packing_orders([$ownerOrder, $o2]);
check('packing slips load orders with their items', count($packed) === 2 && $packed[0]['items'][0]['product_name'] === 'Orla Hoops');
check('packing by status finds processing orders', count(packing_orders([], 'processing')) >= 2 && count(packing_orders([], 'nonsense')) >= 2);


check('throttle allows up to the limit then blocks', throttle_hit('t1', 3, 60) && throttle_hit('t1', 3, 60) && throttle_hit('t1', 3, 60) && !throttle_hit('t1', 3, 60) && throttle_blocked('t1', 3));
throttle_clear('t1');
check('throttle clear resets the counter', !throttle_blocked('t1', 3) && throttle_hit('t1', 3, 60));
$_GET = ['ref' => 'VBABC123'];
check('an order reference in ref is not counted as a traffic source', stats_source() === 'direct');
$_GET = ['utm_source' => 'spam1'];
check('an unknown utm_source is grouped as other', stats_source() === 'other');
$_GET = ['utm_source' => 'Instagram'];
check('a known utm_source is kept', stats_source() === 'instagram');
$_GET = [];
db()->exec("INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, subtotal, shipping, total, promo_code, payment_method, payment_status, order_status) VALUES ('VB-PROMOX1', NULL, 'T', 'dup@e.com', '020', 'a', 'c', 'Ashanti', 100, 25, 125, 'WELCOME10', 'paystack', 'pending', 'pending')");
check('WELCOME10 is blocked for an email that used it', promo_used_by('WELCOME10', null, 'DUP@e.com') === true);
check('WELCOME10 is open to a new email', promo_used_by('WELCOME10', null, 'fresh@e.com') === false);
check('other codes are not limited per customer', promo_used_by('SAVE20', null, 'dup@e.com') === false);
db()->exec("INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, subtotal, shipping, total, payment_method, payment_status, order_status, created_at) VALUES ('VB-CODOLDX', NULL, 'T', 'cod@e.com', '020', 'a', 'c', 'Greater Accra', 100, 25, 125, 'cod', 'pending', 'pending', '2000-01-01 00:00:00')");
$codOld = (int) db()->lastInsertId();
db()->exec("INSERT INTO vb_order_items (order_id, product_id, product_name, unit_price, quantity) VALUES ($codOld, 2, 'Perle Strand', 100, 1)");
db()->exec("INSERT INTO vb_orders (order_ref, user_id, full_name, email, phone, address, city, region, subtotal, shipping, total, payment_method, payment_status, order_status) VALUES ('VB-CODNEWX', NULL, 'T', 'cod2@e.com', '020', 'a', 'c', 'Greater Accra', 100, 25, 125, 'cod', 'pending', 'pending')");
$codNew = (int) db()->lastInsertId();
$codExpired = expire_unpaid_orders();
check('expire_unpaid_orders cancels an old unconfirmed COD order', $codExpired >= 1 && db()->query("SELECT order_status FROM vb_orders WHERE id = $codOld")->fetchColumn() === 'cancelled');
check('a recent COD order is left alone', db()->query("SELECT order_status FROM vb_orders WHERE id = $codNew")->fetchColumn() === 'pending');
check('birthday needs the person to be 13 or older', set_birthday(1, (date('Y') - 5) . '-01-01') !== '' && set_birthday(1, '1999-13-45') !== '');
$newUser = ['birthday' => '1999-' . date('m-d'), 'birthday_used_year' => null, 'created_at' => date('Y-m-d H:i:s')];
check('birthday reward waits for a two week old account', birthday_reward_available($newUser) === false);
$newUser['created_at'] = date('Y-m-d H:i:s', time() - 30 * 86400);
check('birthday reward opens for an older account', birthday_reward_available($newUser) === true);

@unlink($dbFile);
fwrite(STDERR, PHP_EOL . "$pass passed, $fail failed" . PHP_EOL);
exit($fail === 0 ? 0 : 1);
