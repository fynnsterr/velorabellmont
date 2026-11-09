<?php
declare(strict_types=1);

if (defined('VB_BOOT')) {
    return;
}
define('VB_BOOT', true);
define('VB_ROOT', dirname(__DIR__));

$GLOBALS['vb_config'] = require VB_ROOT . '/config.php';

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
    return base_path() . '/assets/' . ltrim($path, '/');
}

function abs_url(string $path = ''): string
{
    $site = rtrim((string) cfg('site.url', ''), '/');
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
        $cats = db()->query('SELECT id, name, slug FROM vb_categories ORDER BY sort_order, name')->fetchAll();
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
        $st = db()->prepare('SELECT id, name, email, phone, role FROM vb_users WHERE id = ?');
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
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
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
    if (!$user || $user['role'] !== 'admin') {
        flash('error', 'Admin access only. Log in with an admin account.');
        redirect('login.php');
    }
    return $user;
}

function safe_next(string $next): string
{
    $allowed = ['checkout.php', 'account.php', 'cart.php', 'shop.php', 'index.php'];
    return in_array($next, $allowed, true) ? $next : 'account.php';
}

function cart_add(int $productId, int $qty): void
{
    $qty = max(1, min(20, $qty));
    $_SESSION['cart'][$productId] = min(20, (int) ($_SESSION['cart'][$productId] ?? 0) + $qty);
}

function cart_set(int $productId, int $qty): void
{
    if ($qty < 1) {
        unset($_SESSION['cart'][$productId]);
        return;
    }
    $_SESSION['cart'][$productId] = min(20, $qty);
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

function cart_count(): int
{
    return array_sum($_SESSION['cart'] ?? []);
}

function cart_lines(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }
    $ids = array_map('intval', array_keys($cart));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM vb_products p JOIN vb_categories c ON c.id = p.category_id WHERE p.active = 1 AND p.id IN ($in)");
    $st->execute($ids);
    $lines = [];
    foreach ($st->fetchAll() as $p) {
        $qty = min((int) $cart[(int) $p['id']], (int) $p['stock']);
        if ($qty < 1) {
            continue;
        }
        $lines[] = [
            'product' => $p,
            'qty' => $qty,
            'line_total' => round((float) $p['price'] * $qty, 2),
        ];
    }
    return $lines;
}

function shipping_cost(float $subtotal): float
{
    if ($subtotal <= 0) {
        return 0.0;
    }
    if ($subtotal >= (float) cfg('shipping.free_over', 500)) {
        return 0.0;
    }
    return (float) cfg('shipping.flat', 25);
}

function cart_totals(array $lines): array
{
    $subtotal = 0.0;
    foreach ($lines as $l) {
        $subtotal += $l['line_total'];
    }
    $subtotal = round($subtotal, 2);
    $shipping = shipping_cost($subtotal);
    return ['subtotal' => $subtotal, 'shipping' => $shipping, 'total' => round($subtotal + $shipping, 2)];
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
    return ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
}

function payment_statuses(): array
{
    return ['pending', 'paid', 'failed'];
}

function badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function new_order_ref(): string
{
    return 'VB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function mark_order_paid(int $orderId, ?string $reference): void
{
    $st = db()->prepare("UPDATE vb_orders SET payment_status = 'paid', paystack_reference = COALESCE(?, paystack_reference), paid_at = CURRENT_TIMESTAMP, order_status = CASE WHEN order_status = 'pending' THEN 'processing' ELSE order_status END WHERE id = ? AND payment_status <> 'paid'");
    $st->execute([$reference, $orderId]);
}

function restore_stock(int $orderId): void
{
    $st = db()->prepare('SELECT product_id, quantity FROM vb_order_items WHERE order_id = ? AND product_id IS NOT NULL');
    $st->execute([$orderId]);
    $up = db()->prepare('UPDATE vb_products SET stock = stock + ? WHERE id = ?');
    foreach ($st->fetchAll() as $row) {
        $up->execute([(int) $row['quantity'], (int) $row['product_id']]);
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
    error_log('[velora] ' . $t->getMessage() . ' in ' . $t->getFile() . ':' . $t->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Something went wrong</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:34rem;margin:4rem auto;padding:0 1rem;line-height:1.6">'
        . '<h1>Something went wrong</h1><p>We could not load this page. Check that the database is running and that config.php has the right details, then refresh.</p>'
        . (cfg('debug', false) ? '<pre style="white-space:pre-wrap;background:#f4f4f4;padding:1rem;border-radius:8px">' . e($t->getMessage()) . '</pre>' : '')
        . '<p><a href="' . e(url('index.php')) . '">Back to the shop</a></p></body>';
    exit;
});

session_name('vb_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; frame-ancestors 'none'; base-uri 'self'");
if (is_https()) {
    header('Strict-Transport-Security: max-age=31536000');
}

require_once __DIR__ . "/paystack.php";