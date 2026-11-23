<?php
declare(strict_types=1);

if (defined('VB_BOOT')) {
    return;
}
define('VB_BOOT', true);
define('VB_ROOT', dirname(__DIR__));

$GLOBALS['vb_config'] = require VB_ROOT . '/config.php';
if (is_file(VB_ROOT . '/config.local.php')) {
    $local = require VB_ROOT . '/config.local.php';
    if (is_array($local)) {
        $GLOBALS['vb_config'] = array_replace_recursive($GLOBALS['vb_config'], $local);
    }
}
foreach ([
    'VB_DB_HOST' => ['db', 'host'], 'VB_DB_NAME' => ['db', 'name'], 'VB_DB_USER' => ['db', 'user'], 'VB_DB_PASS' => ['db', 'pass'],
    'VB_PAYSTACK_PUBLIC' => ['paystack', 'public_key'], 'VB_PAYSTACK_SECRET' => ['paystack', 'secret_key'],
    'VB_SITE_URL' => ['site', 'url'], 'VB_LOG_DIR' => ['log', 'dir'], 'VB_BACKUP_DIR' => ['backup', 'dir'], 'VB_CACHE_DIR' => ['cache', 'dir'], 
] as $env => $path) {
    $v = getenv($env);
    if ($v !== false && $v !== '') {
        $GLOBALS['vb_config'][$path[0]][$path[1]] = $v;
    }
}
if (getenv('VB_NOCACHE')) {
    $GLOBALS['vb_config']['cache']['enabled'] = false;
}
if (getenv('VB_DEBUG')) {
    $GLOBALS['vb_config']['debug'] = true;
}

function cfg(string $path, $default = null)
{
    $node = $GLOBALS['vb_config'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $default;
        }
        $node = $node[$key];
    }
    return $node;
}

require_once __DIR__ . '/log.php';
require_once __DIR__ . '/cache.php';
log_install_handlers();

function cookie_path(): string
{
    $p = cfg('security.cookie_path');
    if (is_string($p) && $p !== '') {
        return '/' . trim($p, '/') . ($p === '/' ? '' : '/');
    }
    return '/';
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $base = '';
    $root = realpath(VB_ROOT);
    $script = !empty($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
    $name = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($root && $script && $name !== '') {
        $rel = str_replace('\\', '/', substr($script, strlen($root)));
        $name = str_replace('\\', '/', $name);
        if ($rel !== '' && strpos(str_replace('\\', '/', $script), str_replace('\\', '/', $root)) === 0
            && substr($name, -strlen($rel)) === $rel) {
            $base = rtrim(substr($name, 0, strlen($name) - strlen($rel)), '/');
            return $base;
        }
    }
    $doc = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($root && $doc) {
        $root = str_replace('\\', '/', $root);
        $doc = rtrim(str_replace('\\', '/', $doc), '/');
        if ($doc !== '' && strpos($root, $doc) === 0) {
            $base = substr($root, strlen($doc));
        }
    }
    return $base;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $rel = ltrim($path, '/');
    $url = base_path() . '/assets/' . $rel;
    if (preg_match('/\.(css|js)$/', $rel)) {
        $file = VB_ROOT . '/assets/' . $rel;
        if (is_file($file)) {
            $url .= '?v=' . filemtime($file);
        }
    }
    return $url;
}

function abs_url(string $path = ''): string
{
    $site = rtrim((string) preg_replace('#/index\.php/*$#i', '', rtrim(trim((string) cfg('site.url', '')), '/')), '/');
    if ($site !== '') {
        return $site . '/' . ltrim($path, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $host)) {
        $host = 'localhost';
    }
    return (is_https() ? 'https' : 'http') . '://' . $host . url($path);
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money($amount): string
{
    return cfg('site.symbol', 'GH₵') . number_format((float) $amount, 2);
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function val(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

function str_in(array $src, string $key, string $default = ''): string
{
    $v = $src[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $sqlite = cfg('db.sqlite_path');
    if ($sqlite) {
        $pdo = new PDO('sqlite:' . $sqlite);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = 'mysql:host=' . cfg('db.host') . ';dbname=' . cfg('db.name') . ';charset=' . cfg('db.charset', 'utf8mb4');
        $pdo = new PDO($dsn, cfg('db.user'), cfg('db.pass'));
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

function categories(): array
{
    static $cats = null;
    if ($cats === null) {
        $cats = cache_remember('categories', fn () => db()->query('SELECT id, name, slug FROM vb_categories ORDER BY sort_order, name')->fetchAll());
    }
    return $cats;
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $st = db()->prepare('SELECT id, name, email, phone, role, birthday, birthday_used_year, referral_code, referred_by, email_verified_at FROM vb_users WHERE id = ?');
        $st->execute([(int) $_SESSION['user_id']]);
        $user = $st->fetch() ?: null;
        if (!$user) {
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function login_user(array $row): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    cart_merge_saved((int) $row['id']);
    wishlist_merge_session((int) $row['id']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
    if (!headers_sent()) {
        setcookie('vb_cart', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('info', 'Log in to continue.');
        redirect('login.php?next=' . urlencode(basename($_SERVER['SCRIPT_NAME'])));
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user || !is_staff($user)) {
        flash('error', 'Staff access only. Log in with a staff account.');
        redirect('login.php');
    }
    $page = basename($_SERVER['SCRIPT_NAME']);
    if (!staff_can($user['role'], $page)) {
        flash('error', 'Your role does not include that page.');
        redirect('admin/index.php');
    }
    static $logged = false;
    if (!$logged && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $logged = true;
        $detail = trim('action=' . val('action') . (val('id') !== '' ? ' id=' . val('id') : ''));
        log_activity('post', $page, $detail, $user);
    }
    return $user;
}

function safe_next(string $next): string
{
    $allowed = ['checkout.php', 'account.php', 'cart.php', 'shop.php', 'index.php', 'wishlist.php'];
    return in_array($next, $allowed, true) ? $next : 'account.php';
}

function cart_key(int $productId, string $size = ''): string
{
    return $size === '' ? (string) $productId : $productId . '|' . $size;
}

function cart_split(string $key): array
{
    $parts = explode('|', $key, 2);
    return [(int) $parts[0], $parts[1] ?? ''];
}

function product_sizes(array $product): array
{
    $raw = trim((string) ($product['sizes'] ?? ''));
    if ($raw === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($s) => $s !== ''));
}

function cart_add(int $productId, int $qty, string $size = ''): void
{
    $qty = max(1, min(20, $qty));
    $key = cart_key($productId, $size);
    $_SESSION['cart'][$key] = min(20, (int) ($_SESSION['cart'][$key] ?? 0) + $qty);
}

function cart_set(string $key, int $qty): void
{
    if ($qty < 1) {
        unset($_SESSION['cart'][$key]);
        return;
    }
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key] = min(20, $qty);
    }
}

function cart_product_qty(int $productId): int
{
    $n = 0;
    foreach ($_SESSION['cart'] ?? [] as $key => $qty) {
        if (cart_split((string) $key)[0] === $productId) {
            $n += (int) $qty;
        }
    }
    return $n;
}

function cart_clear(): void
{
    unset($_SESSION['cart'], $_SESSION['promo'], $_SESSION['redeem'], $_SESSION['gift']);
    cart_persist();
}

function cart_count(): int
{
    return array_sum($_SESSION['cart'] ?? []);
}

function cart_encode(array $cart): string
{
    $out = [];
    foreach ($cart as $key => $qty) {
        $out[] = $key . '*' . (int) $qty;
    }
    return implode(',', $out);
}

function cart_decode(string $raw): array
{
    $cart = [];
    foreach (explode(',', $raw) as $part) {
        if (!preg_match('/^(\d{1,9})(?:\|([A-Za-z0-9 .\-]{1,20}))?\*(\d{1,2})$/', $part, $m)) {
            continue;
        }
        $qty = min(20, (int) $m[3]);
        if ($qty > 0) {
            $cart[cart_key((int) $m[1], $m[2] ?? '')] = $qty;
        }
    }
    return $cart;
}

function cart_persist(): void
{
    $cart = $_SESSION['cart'] ?? [];
    $raw = cart_encode($cart);
    if (!headers_sent()) {
        $opts = ['expires' => $raw === '' ? time() - 3600 : time() + 60 * 86400, 'path' => cookie_path(), 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax'];
        setcookie('vb_cart', $raw, $opts);
    }
    if (!empty($_SESSION['user_id'])) {
        $uid = (int) $_SESSION['user_id'];
        if ($raw === '') {
            db()->prepare('DELETE FROM vb_carts WHERE user_id = ?')->execute([$uid]);
        } else {
            $up = db()->prepare('UPDATE vb_carts SET cart_data = ?, updated_at = CURRENT_TIMESTAMP, reminder_sent = 0 WHERE user_id = ?');
            $up->execute([$raw, $uid]);
            if ($up->rowCount() < 1) {
                $chk = db()->prepare('SELECT 1 FROM vb_carts WHERE user_id = ?');
                $chk->execute([$uid]);
                if (!$chk->fetchColumn()) {
                    db()->prepare('INSERT INTO vb_carts (user_id, cart_data) VALUES (?, ?)')->execute([$uid, $raw]);
                }
            }
        }
    }
}

function cart_restore(): void
{
    if (!empty($_SESSION['cart'])) {
        return;
    }
    $raw = '';
    if (!empty($_SESSION['user_id'])) {
        $st = db()->prepare('SELECT cart_data FROM vb_carts WHERE user_id = ?');
        $st->execute([(int) $_SESSION['user_id']]);
        $raw = (string) ($st->fetchColumn() ?: '');
    }
    if ($raw === '' && !empty($_COOKIE['vb_cart']) && is_string($_COOKIE['vb_cart'])) {
        $raw = $_COOKIE['vb_cart'];
    }
    if ($raw !== '') {
        $cart = cart_decode($raw);
        if ($cart) {
            $_SESSION['cart'] = $cart;
        }
    }
}

function cart_merge_saved(int $userId): void
{
    $st = db()->prepare('SELECT cart_data FROM vb_carts WHERE user_id = ?');
    $st->execute([$userId]);
    $saved = cart_decode((string) ($st->fetchColumn() ?: ''));
    $cart = $_SESSION['cart'] ?? [];
    foreach ($saved as $key => $qty) {
        $cart[$key] = min(20, max((int) ($cart[$key] ?? 0), $qty));
    }
    if ($cart) {
        $_SESSION['cart'] = $cart;
        cart_persist();
    }
}

function cart_lines(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }
    $ids = [];
    foreach (array_keys($cart) as $key) {
        $ids[cart_split((string) $key)[0]] = true;
    }
    $ids = array_keys($ids);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM vb_products p JOIN vb_categories c ON c.id = p.category_id WHERE p.active = 1 AND p.id IN ($in)");
    $st->execute($ids);
    $products = [];
    foreach ($st->fetchAll() as $p) {
        $products[(int) $p['id']] = $p;
    }
    $left = [];
    $lines = [];
    foreach ($cart as $key => $want) {
        [$pid, $size] = cart_split((string) $key);
        if (!isset($products[$pid])) {
            continue;
        }
        $p = $products[$pid];
        $sizes = product_sizes($p);
        if ($sizes && !in_array($size, $sizes, true)) {
            continue;
        }
        $left[$pid] = $left[$pid] ?? (int) $p['stock'];
        $qty = min((int) $want, $left[$pid]);
        if ($qty < 1) {
            continue;
        }
        $left[$pid] -= $qty;
        $lines[] = [
            'key' => (string) $key,
            'product' => $p,
            'size' => $size,
            'qty' => $qty,
            'unit' => price_now($p),
            'line_total' => round(price_now($p) * $qty, 2),
        ];
    }
    return $lines;
}

function delivery_zones(): array
{
    $custom = cfg('shipping.zones', null);
    if (is_array($custom) && $custom) {
        return $custom;
    }
    $flat = (float) cfg('shipping.flat', 25);
    return [
        ['name' => 'Greater Accra', 'regions' => ['Greater Accra'], 'fee' => $flat, 'days' => [1, 2]],
        ['name' => 'Ashanti, Central, Eastern and Western', 'regions' => ['Ashanti', 'Central', 'Eastern', 'Western'], 'fee' => $flat + 15, 'days' => [2, 3]],
        ['name' => 'All other regions', 'regions' => [], 'fee' => $flat + 30, 'days' => [3, 5]],
    ];
}

function delivery_zone(string $region): array
{
    $fallback = null;
    foreach (delivery_zones() as $z) {
        if (in_array($region, $z['regions'], true)) {
            return $z;
        }
        if (!$z['regions']) {
            $fallback = $z;
        }
    }
    $zones = delivery_zones();
    return $fallback ?? end($zones);
}

function add_business_days(DateTimeImmutable $from, int $days): DateTimeImmutable
{
    $d = $from;
    while ($days > 0) {
        $d = $d->modify('+1 day');
        if ((int) $d->format('N') < 6) {
            $days--;
        }
    }
    return $d;
}

function delivery_estimate(string $region, ?DateTimeImmutable $from = null): string
{
    $z = delivery_zone($region);
    $from = $from ?? new DateTimeImmutable('now');
    [$lo, $hi] = $z['days'];
    $a = add_business_days($from, $lo);
    $b = add_business_days($from, $hi);
    $range = $lo === $hi ? $lo . ' working day' . ($lo === 1 ? '' : 's') : $lo . ' to ' . $hi . ' working days';
    return $range . ' (' . $a->format('D j M') . ' to ' . $b->format('D j M') . ')';
}

function shipping_cost(float $subtotal, string $region = 'Greater Accra'): float
{
    if ($subtotal <= 0) {
        return 0.0;
    }
    if ($subtotal >= (float) cfg('shipping.free_over', 500)) {
        return 0.0;
    }
    return round((float) delivery_zone($region)['fee'], 2);
}

function promo_find(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $st = db()->prepare('SELECT * FROM vb_promos WHERE code = ?');
    $st->execute([$code]);
    return $st->fetch() ?: null;
}

function promo_discount(?array $promo, float $subtotal): array
{
    if (!$promo || !(int) $promo['active']) {
        return [0.0, 'That code is not valid.'];
    }
    if (!empty($promo['expires_on']) && $promo['expires_on'] < date('Y-m-d')) {
        return [0.0, 'That code has expired.'];
    }
    if ($promo['max_uses'] !== null && (int) $promo['used_count'] >= (int) $promo['max_uses']) {
        return [0.0, 'That code has been fully used.'];
    }
    if ($subtotal < (float) $promo['min_subtotal']) {
        return [0.0, 'Spend ' . money((float) $promo['min_subtotal']) . ' or more to use ' . $promo['code'] . '.'];
    }
    $d = $promo['kind'] === 'percent' ? $subtotal * (float) $promo['amount'] / 100 : (float) $promo['amount'];
    if (isset($promo['cap'])) {
        $d = min($d, (float) $promo['cap']);
    }
    return [round(min($d, $subtotal), 2), ''];
}

function promo_used_by(string $code, ?int $userId, string $email): bool
{
    if (!in_array(strtoupper($code), array_map('strtoupper', (array) cfg('promos.once_per_customer', [])), true)) {
        return false;
    }
    $sql = "SELECT COUNT(*) FROM vb_orders WHERE promo_code = ? AND order_status <> 'cancelled' AND (LOWER(email) = ?" . ($userId ? ' OR user_id = ?' : '') . ')';
    $args = [strtoupper($code), strtolower(trim($email))];
    if ($userId) {
        $args[] = $userId;
    }
    $st = db()->prepare($sql);
    $st->execute($args);
    return (int) $st->fetchColumn() > 0;
}

function bundle_percent(): float
{
    return max(0.0, min(50.0, (float) cfg('bundle.percent', 10)));
}

function bundle_min_categories(): int
{
    return max(2, (int) cfg('bundle.min_categories', 2));
}

function bundle_categories(array $lines): int
{
    $cats = [];
    foreach ($lines as $l) {
        $cats[(int) ($l['product']['category_id'] ?? 0)] = true;
    }
    return count($cats);
}

function bundle_discount(array $lines, float $subtotal): float
{
    if (bundle_percent() <= 0 || bundle_categories($lines) < bundle_min_categories()) {
        return 0.0;
    }
    return round($subtotal * bundle_percent() / 100, 2);
}

function cart_totals(array $lines, ?string $region = 'Greater Accra', ?array $promo = null, int $points = 0, ?array $gift = null): array
{
    $subtotal = 0.0;
    foreach ($lines as $l) {
        $subtotal += $l['line_total'];
    }
    $subtotal = round($subtotal, 2);
    $discount = 0.0;
    $promoError = '';
    if ($promo) {
        [$discount, $promoError] = promo_discount($promo, $subtotal);
    }
    $promoCode = $discount > 0 && $promo ? $promo['code'] : null;
    if ($discount <= 0) {
        $bundle = bundle_discount($lines, $subtotal);
        if ($bundle > 0) {
            $discount = $bundle;
            $promoCode = 'BUNDLE';
        }
    }
    $after = round($subtotal - $discount, 2);
    $pointsUsed = 0;
    $pointsDiscount = 0.0;
    if ($points > 0 && $after > 0) {
        $unitPoints = max(1, (int) loyalty('redeem_points'));
        $unitValue = max(0.01, (float) loyalty('redeem_value'));
        $units = min(intdiv($points, $unitPoints), (int) floor($after / $unitValue));
        $pointsUsed = $units * $unitPoints;
        $pointsDiscount = round($units * $unitValue, 2);
        $after = round($after - $pointsDiscount, 2);
    }
    $shipping = $region !== null ? shipping_cost($after, $region) : ($after >= (float) cfg('shipping.free_over', 500) ? 0.0 : null);
    $payable = round($after + (float) $shipping, 2);
    $giftAmount = $gift ? round(min((float) $gift['balance'], $payable), 2) : 0.0;
    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'promo_code' => $promoCode,
        'promo_error' => $promoError,
        'points_used' => $pointsUsed,
        'points_discount' => $pointsDiscount,
        'after' => $after,
        'shipping' => $shipping,
        'before_gift' => $payable,
        'gift_amount' => $giftAmount,
        'gift_code' => $giftAmount > 0 ? $gift['code'] : null,
        'total' => round($payable - $giftAmount, 2),
    ];
}

function session_promo(): ?array
{
    if (empty($_SESSION['promo'])) {
        return null;
    }
    if ($_SESSION['promo'] === 'BIRTHDAY') {
        return birthday_reward_available(current_user()) ? birthday_promo() : null;
    }
    return promo_find((string) $_SESSION['promo']);
}

function ghana_regions(): array
{
    return [
        'Greater Accra', 'Ashanti', 'Central', 'Eastern', 'Western', 'Western North', 'Volta', 'Oti',
        'Northern', 'Savannah', 'North East', 'Upper East', 'Upper West', 'Bono', 'Bono East', 'Ahafo',
    ];
}

function order_statuses(): array
{
    return ['pending', 'processing', 'dispatched', 'out_for_delivery', 'delivered', 'cancelled'];
}

function payment_statuses(): array
{
    return ['pending', 'paid', 'failed', 'partially_refunded', 'refunded'];
}

function badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(order_status_label($status)) . '</span>';
}

function pay_badge(string $status): string
{
    $label = $status === 'pending' ? 'Awaiting payment' : order_status_label($status);
    return '<span class="badge badge-' . e($status === 'pending' ? 'pending' : $status) . '">' . e($label) . '</span>';
}

function new_order_ref(): string
{
    return 'VB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function mark_order_paid(int $orderId, ?string $reference): void
{
    $before = order_by_id($orderId);
    $st = db()->prepare("UPDATE vb_orders SET payment_status = 'paid', paystack_reference = COALESCE(?, paystack_reference), paid_at = CURRENT_TIMESTAMP, order_status = CASE WHEN order_status = 'pending' THEN 'processing' ELSE order_status END WHERE id = ? AND payment_status <> 'paid' AND payment_status <> 'partially_refunded' AND payment_status <> 'refunded'");
    $st->execute([$reference, $orderId]);
    if ($st->rowCount() > 0) {
        order_event($orderId, 'paid', $reference);
        if ($before && $before['order_status'] === 'cancelled') {
            order_event($orderId, 'paid_after_cancel', 'Payment arrived after cancellation. Refund this order.');
            vb_log('warning', 'Payment received for a cancelled order', ['order' => $before['order_ref']]);
            return;
        }
        if ($before && $before['order_status'] === 'pending') {
            order_event($orderId, 'processing', 'Started automatically after payment');
        }
        track_order_event('order_paid');
        order_paid_hooks($orderId);
    }
}

function render_error_page(int $code, string $title, string $message): void
{
    http_response_code($code);
    $pageTitle = $title;
    $errorTitle = $title;
    $errorMessage = $message;
    require VB_ROOT . '/includes/error_view.php';
    exit;
}

set_exception_handler(function (Throwable $t) {
    $ref = vb_log('error', get_class($t) . ': ' . $t->getMessage(), ['file' => basename($t->getFile()), 'line' => $t->getLine()]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Something went wrong</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:34rem;margin:4rem auto;padding:0 1rem;line-height:1.6">'
        . '<h1>Something went wrong</h1><p>We could not load this page. Please try again in a moment. If it keeps happening, contact us and quote reference <strong>' . e($ref) . '</strong>.</p>'
        . (cfg('debug', false) ? '<pre style="white-space:pre-wrap;background:#f4f4f4;padding:1rem;border-radius:8px">' . e($t->getMessage()) . '</pre>' : '')
        . '<p><a href="' . e(url('index.php')) . '">Back to the shop</a></p></body></html>';
    exit;
});

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('vb_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => cookie_path(),
    'secure' => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
cart_restore();

function csp_policy(): string
{
    $script = ["'self'"];
    $connect = ["'self'"];
    $img = ["'self'", 'data:'];
    if (trim((string) cfg('analytics.ga4_id', '')) !== '') {
        $script[] = 'https://www.googletagmanager.com';
        $connect[] = 'https://*.google-analytics.com';
        $connect[] = 'https://*.analytics.google.com';
        $connect[] = 'https://www.googletagmanager.com';
        $img[] = 'https://*.google-analytics.com';
        $img[] = 'https://www.googletagmanager.com';
    }
    if (preg_match('/^\d{8,20}$/', trim((string) cfg('analytics.meta_pixel_id', '')))) {
        $script[] = 'https://connect.facebook.net';
        $connect[] = 'https://www.facebook.com';
        $connect[] = 'https://connect.facebook.net';
        $img[] = 'https://www.facebook.com';
    }
    if (trim((string) cfg('analytics.plausible_domain', '')) !== '') {
        $script[] = 'https://plausible.io';
        $connect[] = 'https://plausible.io';
    }
    return "default-src 'self'; img-src " . implode(' ', $img) . "; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src " . implode(' ', $script) . '; connect-src ' . implode(' ', $connect) . "; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";
}

if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Content-Security-Policy: ' . csp_policy());
    header_remove('X-Powered-By');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

require_once __DIR__ . "/throttle.php";
require_once __DIR__ . "/paystack.php";
require_once __DIR__ . "/store.php";
require_once __DIR__ . "/rewards.php";
require_once __DIR__ . "/mail.php";
require_once __DIR__ . "/ops.php";
require_once __DIR__ . "/analytics.php";
require_once __DIR__ . "/export.php";
require_once __DIR__ . "/comms.php";
require_once __DIR__ . "/lifecycle.php";
require_once __DIR__ . "/whatsapp.php";
require_once __DIR__ . "/staff.php";
require_once __DIR__ . "/copycheck.php";
require_once __DIR__ . "/images.php";
require_once __DIR__ . "/gallery.php";
require_once __DIR__ . "/social.php";
require_once __DIR__ . "/search.php";
require_once __DIR__ . "/trust.php";
require_once __DIR__ . "/seo.php";
require_once __DIR__ . "/stats.php";
require_once __DIR__ . "/backup.php";
require_once __DIR__ . "/auth.php";

