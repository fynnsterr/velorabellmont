<?php
function product_select_sql(): string
{
    return 'SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM vb_products p JOIN vb_categories c ON c.id = p.category_id';
}

function new_arrivals(int $limit = 4): array
{
    $st = db()->prepare(product_select_sql() . ' WHERE p.active = 1 ORDER BY p.created_at DESC, p.id DESC LIMIT ' . (int) $limit);
    $st->execute();
    return $st->fetchAll();
}

function best_sellers(int $limit = 4): array
{
    $sql = product_select_sql() . ' JOIN (
        SELECT oi.product_id AS pid, SUM(oi.quantity) AS sold
        FROM vb_order_items oi JOIN vb_orders o ON o.id = oi.order_id
        WHERE oi.product_id IS NOT NULL AND o.order_status <> \'cancelled\' AND (o.payment_status = \'paid\' OR o.order_status = \'delivered\')
        GROUP BY oi.product_id
    ) s ON s.pid = p.id WHERE p.active = 1 ORDER BY s.sold DESC, p.id LIMIT ' . (int) $limit;
    return db()->query($sql)->fetchAll();
}

function featured_picks(int $limit = 4): array
{
    $st = db()->prepare(product_select_sql() . ' WHERE p.active = 1 AND p.featured = 1 ORDER BY p.id LIMIT ' . (int) $limit);
    $st->execute();
    return $st->fetchAll();
}

function velora_edit_definitions(): array
{
    return [
        ['key' => 'wedding', 'title' => 'Weddings', 'line' => 'Pearls and crystal for a day of photographs.', 'slugs' => ['perle-strand', 'gala-drop-earrings', 'celeste-tennis-bracelet']],
        ['key' => 'birthday', 'title' => 'Birthdays', 'line' => 'Something personal, light and easy to gift.', 'slugs' => ['luna-disc-necklace', 'petite-pearl-studs', 'rosalind-twist-ring']],
        ['key' => 'graduation', 'title' => 'Graduations', 'line' => 'Polished pieces for a proud day.', 'slugs' => ['solene-pendant', 'petite-pearl-studs', 'aria-link-bracelet']],
        ['key' => 'everyday', 'title' => 'Everyday wear', 'line' => 'Easy gold tones you can wear on repeat.', 'slugs' => ['luna-disc-necklace', 'orla-hoops', 'maree-bead-bracelet']],
        ['key' => 'special', 'title' => 'Special occasions', 'line' => 'Bold pieces for nights you want to be noticed.', 'slugs' => ['aurelia-layered-collar', 'gala-drop-earrings', 'obsidienne-signet-ring']],
    ];
}

function velora_edit(): array
{
    $defs = velora_edit_definitions();
    $slugs = [];
    foreach ($defs as $d) {
        foreach ($d['slugs'] as $s) {
            $slugs[$s] = true;
        }
    }
    $slugs = array_keys($slugs);
    $in = implode(',', array_fill(0, count($slugs), '?'));
    $st = db()->prepare(product_select_sql() . " WHERE p.active = 1 AND p.slug IN ($in)");
    $st->execute($slugs);
    $by = [];
    foreach ($st->fetchAll() as $p) {
        $by[$p['slug']] = $p;
    }
    $sets = [];
    foreach ($defs as $d) {
        $items = [];
        foreach ($d['slugs'] as $s) {
            if (isset($by[$s])) {
                $items[] = $by[$s];
            }
        }
        if (count($items) < 2) {
            continue;
        }
        $total = 0.0;
        $available = true;
        foreach ($items as $p) {
            $total += price_now($p);
            if ((int) $p['stock'] < 1) {
                $available = false;
            }
        }
        $sets[] = ['key' => $d['key'], 'title' => $d['title'], 'line' => $d['line'], 'items' => $items, 'total' => $total, 'available' => $available];
    }
    return $sets;
}

function can_review(int $userId, int $productId): bool
{
    $st = db()->prepare("SELECT COUNT(*) FROM vb_orders o JOIN vb_order_items oi ON oi.order_id = o.id
        WHERE o.user_id = ? AND oi.product_id = ? AND o.order_status <> 'cancelled' AND (o.payment_status = 'paid' OR o.order_status = 'delivered')");
    $st->execute([$userId, $productId]);
    return (int) $st->fetchColumn() > 0;
}

function reviewer_label(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = $parts[0] ?? 'Customer';
    $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) . '.' : '';
    return trim($first . ' ' . $last);
}

function product_reviews(int $productId): array
{
    $st = db()->prepare('SELECT r.*, u.name AS user_name FROM vb_reviews r JOIN vb_users u ON u.id = r.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC, r.id DESC');
    $st->execute([$productId]);
    return $st->fetchAll();
}

function review_summary(int $productId): array
{
    $st = db()->prepare('SELECT COUNT(*) AS n, AVG(rating) AS avg_rating FROM vb_reviews WHERE product_id = ?');
    $st->execute([$productId]);
    $row = $st->fetch();
    return ['count' => (int) $row['n'], 'average' => $row['n'] ? round((float) $row['avg_rating'], 1) : 0.0];
}

function latest_reviews(int $limit = 3): array
{
    $st = db()->prepare('SELECT r.*, u.name AS user_name, p.name AS product_name, p.slug AS product_slug FROM vb_reviews r JOIN vb_users u ON u.id = r.user_id JOIN vb_products p ON p.id = r.product_id WHERE r.rating >= 4 ORDER BY r.created_at DESC, r.id DESC LIMIT ' . (int) $limit);
    $st->execute();
    return $st->fetchAll();
}

function save_review(int $userId, int $productId, int $rating, string $comment, ?string $photo = null, bool $removePhoto = false): bool
{
    $comment = trim($comment);
    if ($rating < 1 || $rating > 5 || $comment === '' || mb_strlen($comment) > 600 || !can_review($userId, $productId)) {
        return false;
    }
    $st = db()->prepare('SELECT id FROM vb_reviews WHERE product_id = ? AND user_id = ?');
    $st->execute([$productId, $userId]);
    $id = $st->fetchColumn();
    if ($id) {
        $old = db()->prepare('SELECT photo FROM vb_reviews WHERE id = ?');
        $old->execute([(int) $id]);
        $oldPhoto = $old->fetchColumn() ?: null;
        $newPhoto = $photo !== null ? $photo : ($removePhoto ? null : $oldPhoto);
        db()->prepare('UPDATE vb_reviews SET rating = ?, comment = ?, photo = ? WHERE id = ?')->execute([$rating, $comment, $newPhoto, (int) $id]);
        if ($oldPhoto && $oldPhoto !== $newPhoto) {
            upload_image_remove($oldPhoto);
        }
    } else {
        db()->prepare('INSERT INTO vb_reviews (product_id, user_id, rating, comment, photo) VALUES (?, ?, ?, ?, ?)')->execute([$productId, $userId, $rating, $comment, $photo]);
        points_add($userId, (int) loyalty('review_points'), 'review:' . $productId);
    }
    return true;
}

function stars(float $rating): string
{
    $full = (int) round($rating);
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}

function product_specs(array $product): array
{
    $out = [];
    foreach (preg_split('/\r\n|\r|\n|\|/', (string) ($product['specs'] ?? '')) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $pos = strpos($line, ':');
        if ($pos === false) {
            $out[] = ['label' => 'Detail', 'value' => $line];
        } else {
            $out[] = ['label' => trim(substr($line, 0, $pos)), 'value' => trim(substr($line, $pos + 1))];
        }
    }
    return $out;
}

function product_gallery(array $product): array
{
    return [['id' => 0, 'image' => $product['image']]];
}

function gift_budget_steps(): array
{
    return [100, 150, 200, 300, 500];
}

function gift_products_under(float $max): array
{
    $st = db()->prepare(product_select_sql() . ' WHERE p.active = 1 AND p.stock > 0');
    $st->execute();
    $out = array_values(array_filter($st->fetchAll(), fn ($p) => price_now($p) <= $max));
    usort($out, fn ($a, $b) => ((int) $b['featured'] <=> (int) $a['featured']) ?: (price_now($b) <=> price_now($a)));
    return $out;
}

function free_delivery_bar(float $after, float $freeOver): string
{
    if ($freeOver <= 0) {
        return '';
    }
    $pct = (int) max(0, min(100, floor($after / $freeOver * 100)));
    $done = $after >= $freeOver;
    $label = $done ? 'You have unlocked free delivery.' : 'Add ' . money($freeOver - $after) . ' more for free delivery.';
    return '<div class="fd-bar' . ($done ? ' is-done' : '') . '"">'
        . '<p class="small fd-text">' . e($label) . '</p>'
        . '<div class="fd-track" role="progressbar" aria-label="Progress to free delivery" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $pct . '"><span class="fd-fill" style="width:' . $pct . '%"></span></div>'
        . '</div>';
}

function stock_state(int $stock): array
{
    if ($stock < 1) {
        return ['state' => 'out', 'text' => 'Sold out'];
    }
    if ($stock <= 5) {
        return ['state' => 'low', 'text' => 'Only ' . $stock . ' left'];
    }
    return ['state' => 'in', 'text' => 'In stock'];
}

function remember_viewed(int $productId): void
{
    $list = array_values(array_diff($_SESSION['viewed'] ?? [], [$productId]));
    array_unshift($list, $productId);
    $_SESSION['viewed'] = array_slice($list, 0, 10);
}

function recently_viewed(int $excludeId = 0, int $limit = 4): array
{
    $ids = array_values(array_diff($_SESSION['viewed'] ?? [], [$excludeId]));
    $ids = array_slice(array_map('intval', $ids), 0, $limit);
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare(product_select_sql() . " WHERE p.active = 1 AND p.id IN ($in)");
    $st->execute($ids);
    $byId = [];
    foreach ($st->fetchAll() as $p) {
        $byId[(int) $p['id']] = $p;
    }
    $out = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $out[] = $byId[$id];
        }
    }
    return $out;
}

function related_products(array $product, int $limit = 4): array
{
    $st = db()->prepare(product_select_sql() . ' WHERE p.active = 1 AND p.category_id = ? AND p.id <> ? ORDER BY p.stock > 0 DESC, ABS(p.price - ?) LIMIT ' . (int) $limit);
    $st->execute([(int) $product['category_id'], (int) $product['id'], (float) $product['price']]);
    return $st->fetchAll();
}

function also_like(array $product, int $limit = 4): array
{
    $st = db()->prepare(product_select_sql() . ' WHERE p.active = 1 AND p.stock > 0 AND p.category_id <> ? AND p.id <> ? ORDER BY ABS(p.price - ?), p.id LIMIT ' . (int) $limit);
    $st->execute([(int) $product['category_id'], (int) $product['id'], (float) $product['price']]);
    return $st->fetchAll();
}

function look_partners(): array
{
    return [
        'necklaces' => ['earrings', 'bracelets'],
        'bracelets' => ['necklaces', 'earrings'],
        'rings' => ['earrings', 'bracelets'],
        'earrings' => ['necklaces', 'bracelets'],
        'statement-pieces' => ['earrings', 'rings'],
    ];
}

function complete_the_look(array $product): array
{
    $partners = look_partners()[$product['category_slug']] ?? [];
    $items = [];
    foreach ($partners as $slug) {
        $st = db()->prepare(product_select_sql() . ' WHERE p.active = 1 AND p.stock > 0 AND c.slug = ? AND p.id <> ? ORDER BY ABS(p.price - ?), p.id LIMIT 1');
        $st->execute([$slug, (int) $product['id'], (float) $product['price']]);
        if ($row = $st->fetch()) {
            $items[] = $row;
        }
    }
    return $items;
}

function set_size_fields(array $items): string
{
    $html = '';
    foreach ($items as $p) {
        $sizes = product_sizes($p);
        if (!$sizes) {
            continue;
        }
        $id = 'set-size-' . (int) $p['id'] . '-' . substr(md5(uniqid('', true)), 0, 5);
        $html .= '<div class="set-size"><label for="' . e($id) . '">' . e($p['name']) . ' size</label><select id="' . e($id) . '" name="size[' . (int) $p['id'] . ']">';
        foreach ($sizes as $s) {
            $html .= '<option value="' . e($s) . '">' . e($s) . '</option>';
        }
        $html .= '</select></div>';
    }
    return $html;
}

function wishlist_ids(): array
{
    if (!empty($_SESSION['user_id'])) {
        $st = db()->prepare('SELECT product_id FROM vb_wishlist WHERE user_id = ? ORDER BY id DESC');
        $st->execute([(int) $_SESSION['user_id']]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    return array_map('intval', $_SESSION['wishlist'] ?? []);
}

function in_wishlist(int $productId): bool
{
    static $cache = null;
    if ($cache === null) {
        $cache = array_flip(wishlist_ids());
    }
    return isset($cache[$productId]);
}

function wishlist_toggle(int $productId): bool
{
    $on = in_array($productId, wishlist_ids(), true);
    if (!empty($_SESSION['user_id'])) {
        $uid = (int) $_SESSION['user_id'];
        if ($on) {
            db()->prepare('DELETE FROM vb_wishlist WHERE user_id = ? AND product_id = ?')->execute([$uid, $productId]);
        } else {
            db()->prepare('INSERT INTO vb_wishlist (user_id, product_id) VALUES (?, ?)')->execute([$uid, $productId]);
        }
    } else {
        $list = array_diff($_SESSION['wishlist'] ?? [], [$productId]);
        if (!$on) {
            $list[] = $productId;
        }
        $_SESSION['wishlist'] = array_values($list);
    }
    return !$on;
}

function wishlist_merge_session(int $userId): void
{
    foreach (array_slice($_SESSION['wishlist'] ?? [], 0, 100) as $pid) {
        $pid = (int) $pid;
        $chk = db()->prepare('SELECT 1 FROM vb_wishlist WHERE user_id = ? AND product_id = ?');
        $chk->execute([$userId, $pid]);
        $exists = db()->prepare('SELECT 1 FROM vb_products WHERE id = ?');
        $exists->execute([$pid]);
        if (!$chk->fetchColumn() && $exists->fetchColumn()) {
            db()->prepare('INSERT INTO vb_wishlist (user_id, product_id) VALUES (?, ?)')->execute([$userId, $pid]);
        }
    }
    unset($_SESSION['wishlist']);
}

function wishlist_products(): array
{
    $ids = wishlist_ids();
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare(product_select_sql() . " WHERE p.active = 1 AND p.id IN ($in)");
    $st->execute($ids);
    $byId = [];
    foreach ($st->fetchAll() as $p) {
        $byId[(int) $p['id']] = $p;
    }
    $out = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $out[] = $byId[$id];
        }
    }
    return $out;
}

function order_items_for(int $orderId): array
{
    $st = db()->prepare('SELECT * FROM vb_order_items WHERE order_id = ? ORDER BY id');
    $st->execute([$orderId]);
    return $st->fetchAll();
}

function pay_label(array $order): string
{
    if ($order['payment_method'] === 'cod') {
        return 'Pay on delivery';
    }
    if ($order['payment_method'] === 'giftcard') {
        return 'Gift card';
    }
    return match ($order['pay_channel'] ?? '') {
        'mobile_money' => 'Mobile money (MTN MoMo, Telecel Cash, AT Money)',
        'card' => 'Card (Visa or Mastercard)',
        default => 'Card or mobile money',
    };
}

function order_summary_text(array $order, array $items): string
{
    $lines = [];
    foreach ($items as $it) {
        $name = $it['product_name'] . (!empty($it['size']) ? ' (size ' . $it['size'] . ')' : '');
        $lines[] = '- ' . $name . ' x ' . (int) $it['quantity'] . ': ' . money((float) $it['unit_price'] * (int) $it['quantity']);
    }
    $out = "Order " . $order['order_ref'] . "\n" . implode("\n", $lines) . "\n";
    $out .= 'Subtotal: ' . money($order['subtotal']) . "\n";
    if ((float) ($order['discount'] ?? 0) > 0) {
        $out .= 'Discount' . (!empty($order['promo_code']) ? ' (' . $order['promo_code'] . ')' : '') . ': -' . money($order['discount']) . "\n";
    }
    if ((int) ($order['points_used'] ?? 0) > 0) {
        $out .= 'Points (' . (int) $order['points_used'] . '): -' . money($order['points_discount']) . "\n";
    }
    if ((float) ($order['gift_amount'] ?? 0) > 0) {
        $out .= 'Gift card: -' . money($order['gift_amount']) . "\n";
    }
    $out .= 'Delivery: ' . ((float) $order['shipping'] > 0 ? money($order['shipping']) : 'Free') . "\n";
    $out .= 'Total: ' . money($order['total']) . "\n";
    return $out;
}

function whatsapp_link(array $order, array $items): string
{
    $msg = "Hello " . cfg('site.name') . ", this is my order.\n" . order_summary_text($order, $items) . 'Delivery to: ' . $order['city'] . ', ' . $order['region'];
    return wa_url($msg);
}
