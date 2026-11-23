<?php

function staff_roles(): array
{
    return ['admin' => 'Admin', 'fulfilment' => 'Fulfilment', 'support' => 'Support', 'marketing' => 'Marketing'];
}

function is_staff($user): bool
{
    return is_array($user) && isset(staff_roles()[(string) ($user['role'] ?? '')]);
}

function staff_pages(string $role): ?array
{
    $map = [
        'admin' => null,
        'fulfilment' => ['index.php', 'orders.php', 'order.php', 'packing.php', 'inventory.php', 'stock.php'],
        'support' => ['index.php', 'orders.php', 'order.php', 'customers.php', 'customer.php', 'enquiries.php', 'reviews.php', 'messages.php'],
        'marketing' => ['index.php', 'products.php', 'product_edit.php', 'collections.php', 'collection_edit.php', 'gallery.php', 'campaigns.php', 'campaign_edit.php', 'promos.php', 'analytics.php', 'reports.php', 'traffic.php', 'reviews.php'],
    ];
    return array_key_exists($role, $map) ? $map[$role] : [];
}

function staff_can(string $role, string $page): bool
{
    $pages = staff_pages($role);
    return $pages === null || in_array($page, $pages, true);
}

function staff_can_action(string $role, string $action): bool
{
    if ($role === 'admin') {
        return true;
    }
    return $role === 'fulfilment' && in_array($action, ['status', 'shipping'], true);
}

function log_activity(string $action, string $target = '', string $detail = '', ?array $user = null): void
{
    try {
        $user = $user ?? current_user();
        $ip = client_ip();
        db()->prepare('INSERT INTO vb_activity (user_id, user_name, role, action, target, detail, ip) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
            $user ? (int) $user['id'] : null,
            mb_substr($user ? (string) $user['name'] : 'system', 0, 80),
            $user ? mb_substr((string) $user['role'], 0, 12) : 'system',
            mb_substr($action, 0, 40),
            $target !== '' ? mb_substr($target, 0, 80) : null,
            $detail !== '' ? mb_substr($detail, 0, 255) : null,
            $ip !== '' ? mb_substr($ip, 0, 45) : null,
        ]);
    } catch (Throwable $t) {
    }
}

function activity_recent(int $limit = 200, int $userId = 0): array
{
    try {
        $sql = 'SELECT * FROM vb_activity' . ($userId > 0 ? ' WHERE user_id = ?' : '') . ' ORDER BY id DESC LIMIT ' . (int) $limit;
        $st = db()->prepare($sql);
        $st->execute($userId > 0 ? [$userId] : []);
        return $st->fetchAll();
    } catch (Throwable $t) {
        return [];
    }
}

function staff_list(): array
{
    return db()->query("SELECT id, name, email, role, created_at FROM vb_users WHERE role <> 'customer' ORDER BY role, name")->fetchAll();
}

function staff_set_role(string $email, string $role, int $actorId): array
{
    $email = strtolower(trim($email));
    if ($role !== 'customer' && !isset(staff_roles()[$role])) {
        return [false, 'Choose a role.'];
    }
    $st = db()->prepare('SELECT id, name, role FROM vb_users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u) {
        return [false, 'No account uses that email. Ask them to register on the shop first, then add them here.'];
    }
    if ((int) $u['id'] === $actorId && $role !== 'admin') {
        return [false, 'You cannot remove your own admin access.'];
    }
    if ($u['role'] === 'admin' && $role !== 'admin') {
        $admins = (int) db()->query("SELECT COUNT(*) FROM vb_users WHERE role = 'admin'")->fetchColumn();
        if ($admins <= 1) {
            return [false, 'There must always be at least one admin.'];
        }
    }
    db()->prepare('UPDATE vb_users SET role = ? WHERE id = ?')->execute([$role, (int) $u['id']]);
    log_activity('staff_role', $email, $u['role'] . ' to ' . $role);
    return [true, $role === 'customer' ? $u['name'] . ' is no longer staff.' : $u['name'] . ' is now ' . staff_roles()[$role] . '.'];
}

function owner_alert_email(): string
{
    return trim((string) (cfg('alerts.order_email', '') ?: cfg('stock.alert_email', '') ?: cfg('site.support_email', '')));
}

function owner_new_order_alert(int $orderId, ?callable $sender = null, ?callable $waTransport = null): bool
{
    $order = order_by_id($orderId);
    if (!$order || (string) $order['kind'] !== 'shop') {
        return false;
    }
    $to = owner_alert_email();
    if ($to === '' || !lifecycle_claim($to, 'owner_order', (string) $orderId)) {
        return false;
    }
    $lines = [];
    foreach (order_items_for($orderId) as $it) {
        $lines[] = (int) $it['quantity'] . ' x ' . $it['product_name'] . (!empty($it['size']) ? ' (size ' . $it['size'] . ')' : '') . '  ' . money((float) $it['unit_price'] * (int) $it['quantity']);
    }
    $cod = (string) $order['payment_method'] === 'cod';
    $text = 'New order ' . $order['order_ref'] . ' for ' . money($order['total']) . ($cod ? ' (pay on delivery, collect ' . money($order['total']) . ')' : ' (paid)') . "\n\n"
        . implode("\n", $lines) . "\n\n"
        . $order['full_name'] . ', ' . $order['phone'] . "\n"
        . $order['address'] . ', ' . $order['city'] . ', ' . $order['region'] . "\n"
        . (!empty($order['notes']) ? 'Customer note: ' . $order['notes'] . "\n" : '')
        . "\nOpen the order: " . abs_url('admin/order.php?id=' . $orderId)
        . "\nPrint the packing slip: " . abs_url('admin/packing.php?id=' . $orderId);
    $sender = $sender ?? 'send_mail';
    $sent = (bool) $sender($to, 'New order ' . $order['order_ref'] . ' · ' . money($order['total']), $text, ['type' => 'owner_order', 'order_id' => $orderId]);
    if (!$sent) {
        lifecycle_release($to, 'owner_order', (string) $orderId);
    }
    wa_owner_alert($order, $waTransport);
    return $sent;
}

function wa_owner_alert(array $order, ?callable $transport = null): bool
{
    if (!wa_enabled()) {
        return false;
    }
    $to = wa_phone_e164((string) cfg('alerts.whatsapp_to', ''));
    if ($to === '') {
        return false;
    }
    try {
        db()->prepare('INSERT INTO vb_wa_log (order_id, event, phone, status) VALUES (?, ?, ?, ?)')->execute([(int) $order['id'], 'owner_alert', $to, 'pending']);
    } catch (PDOException $e) {
        return false;
    }
    [$ok, $detail] = wa_send_template($to, (string) cfg('alerts.whatsapp_template', 'velora_new_order_owner'), [wa_clean_param((string) $order['order_ref']), wa_clean_param((string) $order['full_name']), wa_clean_param(money($order['total']))], $transport);
    db()->prepare('UPDATE vb_wa_log SET status = ?, detail = ? WHERE order_id = ? AND event = ?')->execute([$ok ? 'sent' : 'failed', $ok ? null : $detail, (int) $order['id'], 'owner_alert']);
    return $ok;
}

function backup_copy_out(): array
{
    $dir = rtrim((string) cfg('backup.copy_dir', ''), '/\\');
    if ($dir === '') {
        return [null, 'No copy folder set (backup.copy_dir).'];
    }
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return [false, 'Cannot create ' . $dir];
    }
    $n = 0;
    $seen = [];
    foreach (backup_list() as $b) {
        $type = (string) $b['type'];
        $seen[$type] = ($seen[$type] ?? 0) + 1;
        if ($seen[$type] > 2) {
            continue;
        }
        $src = backup_path((string) $b['name']);
        if ($src === null) {
            continue;
        }
        $dst = $dir . '/' . basename($src);
        if (!is_file($dst) && @copy($src, $dst)) {
            $n++;
        }
    }
    return [true, $n . ' file(s) copied to ' . $dir];
}

function bulk_columns(): array
{
    return ['slug', 'name', 'category', 'price', 'cost_price', 'stock', 'low_stock_at', 'active'];
}

function bulk_export_csv(): string
{
    $rows = db()->query('SELECT p.slug, p.name, c.name AS category, p.price, p.cost_price, p.stock, p.low_stock_at, p.active FROM vb_products p JOIN vb_categories c ON c.id = p.category_id ORDER BY p.id')->fetchAll();
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, bulk_columns());
    foreach ($rows as $r) {
        fputcsv($fh, [$r['slug'], $r['name'], $r['category'], $r['price'], $r['cost_price'] ?? '', $r['stock'], $r['low_stock_at'] ?? '', $r['active']]);
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return "\xEF\xBB\xBF" . $csv;
}

function bulk_slug(string $text): string
{
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $t = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $t !== false ? $t : $text), '-'));
    return $t !== '' ? $t : 'product';
}

function bulk_parse(string $csv): array
{
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, (string) $csv);
    rewind($fh);
    $head = fgetcsv($fh);
    if (!$head) {
        fclose($fh);
        return [[], 'The file is empty.'];
    }
    $head = array_map(fn ($h) => strtolower(trim((string) $h)), $head);
    if (!in_array('slug', $head, true) && !in_array('name', $head, true)) {
        fclose($fh);
        return [[], 'The first row must be column names, including slug or name. Export the products first and edit that file.'];
    }
    $rows = [];
    while (($r = fgetcsv($fh)) !== false) {
        if (count($r) === 1 && trim((string) $r[0]) === '') {
            continue;
        }
        $row = [];
        foreach ($head as $i => $h) {
            $row[$h] = trim((string) ($r[$i] ?? ''));
        }
        $rows[] = $row;
        if (count($rows) > 500) {
            fclose($fh);
            return [[], 'Up to 500 rows at a time, please split the file.'];
        }
    }
    fclose($fh);
    return [$rows, null];
}

function bulk_bool(string $v): ?int
{
    $v = strtolower($v);
    if (in_array($v, ['1', 'yes', 'y', 'true', 'shown'], true)) {
        return 1;
    }
    if (in_array($v, ['0', 'no', 'n', 'false', 'hidden'], true)) {
        return 0;
    }
    return null;
}

function bulk_plan(array $rows): array
{
    $cats = [];
    foreach (db()->query('SELECT id, name, slug FROM vb_categories')->fetchAll() as $c) {
        $cats[strtolower($c['name'])] = (int) $c['id'];
        $cats[strtolower($c['slug'])] = (int) $c['id'];
    }
    $find = db()->prepare('SELECT * FROM vb_products WHERE slug = ?');
    $plan = [];
    $seen = [];
    foreach ($rows as $i => $r) {
        $n = $i + 2;
        $slug = (string) ($r['slug'] ?? '');
        $name = (string) ($r['name'] ?? '');
        $p = false;
        if ($slug !== '') {
            $find->execute([$slug]);
            $p = $find->fetch();
        }
        if ($slug !== '' && isset($seen[$slug])) {
            $plan[] = ['type' => 'error', 'row' => $n, 'name' => $slug, 'msg' => 'Same slug appears twice in this file.'];
            continue;
        }
        $seen[$slug] = true;
        $in = [];
        $err = null;
        if (($r['price'] ?? '') !== '') {
            $v = str_replace(',', '', $r['price']);
            if (!is_numeric($v) || (float) $v <= 0 || (float) $v > 1000000) {
                $err = 'Price must be a number above 0.';
            } else {
                $in['price'] = round((float) $v, 2);
            }
        }
        if (!$err && ($r['cost_price'] ?? '') !== '') {
            $v = str_replace(',', '', $r['cost_price']);
            if (!is_numeric($v) || (float) $v < 0 || (float) $v > 1000000) {
                $err = 'Cost price must be a number, 0 or more.';
            } else {
                $in['cost_price'] = round((float) $v, 2);
            }
        }
        if (!$err && ($r['stock'] ?? '') !== '') {
            if (!ctype_digit($r['stock']) || (int) $r['stock'] > 100000) {
                $err = 'Stock must be a whole number, 0 or more.';
            } else {
                $in['stock'] = (int) $r['stock'];
            }
        }
        if (!$err && ($r['low_stock_at'] ?? '') !== '') {
            if (!ctype_digit($r['low_stock_at']) || (int) $r['low_stock_at'] > 100000) {
                $err = 'Low stock level must be a whole number.';
            } else {
                $in['low_stock_at'] = (int) $r['low_stock_at'];
            }
        }
        if (!$err && ($r['active'] ?? '') !== '') {
            $b = bulk_bool($r['active']);
            if ($b === null) {
                $err = 'Active must be 1 or 0 (or yes / no).';
            } else {
                $in['active'] = $b;
            }
        }
        if ($err) {
            $plan[] = ['type' => 'error', 'row' => $n, 'name' => $slug !== '' ? $slug : $name, 'msg' => $err];
            continue;
        }
        if ($p) {
            $changes = [];
            foreach ($in as $k => $new) {
                $old = $p[$k];
                $same = $old !== null && $old !== '' && (is_numeric($old) ? abs((float) $old - (float) $new) < 0.005 : (string) $old === (string) $new);
                if (!$same) {
                    $changes[$k] = [$old, $new];
                }
            }
            $plan[] = ['type' => $changes ? 'update' : 'same', 'row' => $n, 'id' => (int) $p['id'], 'name' => $p['name'], 'changes' => $changes];
            continue;
        }
        $catName = strtolower((string) ($r['category'] ?? ''));
        if ($name === '' || !isset($in['price']) || !isset($cats[$catName])) {
            $plan[] = ['type' => 'error', 'row' => $n, 'name' => $slug !== '' ? $slug : ($name !== '' ? $name : '(blank)'), 'msg' => $slug !== '' ? 'No product has that slug. To add a new one, give name, a category that exists, and a price.' : 'To add a new product, give name, a category that exists, and a price.'];
            continue;
        }
        $plan[] = ['type' => 'create', 'row' => $n, 'name' => $name, 'slug' => $slug !== '' ? bulk_slug($slug) : bulk_slug($name), 'category_id' => $cats[$catName], 'data' => $in];
    }
    return $plan;
}

function bulk_apply(array $plan, ?int $adminId): array
{
    $upd = 0;
    $new = 0;
    $failed = [];
    foreach ($plan as $p) {
        if ($p['type'] === 'update') {
            $sets = [];
            $args = [];
            foreach ($p['changes'] as $k => [$old, $val]) {
                if ($k === 'stock') {
                    continue;
                }
                $sets[] = $k . ' = ?';
                $args[] = $val;
            }
            if ($sets) {
                $args[] = $p['id'];
                db()->prepare('UPDATE vb_products SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
            }
            if (isset($p['changes']['stock'])) {
                $cur = db()->prepare('SELECT stock FROM vb_products WHERE id = ?');
                $cur->execute([$p['id']]);
                $delta = (int) $p['changes']['stock'][1] - (int) $cur->fetchColumn();
                if ($delta !== 0) {
                    $r = stock_adjust($p['id'], $delta, 'adjust', 'Bulk CSV import', $adminId);
                    if (!$r[0]) {
                        $failed[] = $p['name'] . ': ' . $r[1];
                        continue;
                    }
                }
            }
            $upd++;
        } elseif ($p['type'] === 'create') {
            $slug = $p['slug'];
            $base = $slug;
            $i = 2;
            $chk = db()->prepare('SELECT COUNT(*) FROM vb_products WHERE slug = ?');
            while (true) {
                $chk->execute([$slug]);
                if ((int) $chk->fetchColumn() === 0) {
                    break;
                }
                $slug = $base . '-' . $i++;
            }
            $d = $p['data'];
            db()->prepare('INSERT INTO vb_products (category_id, name, slug, description, price, stock, metal, image, cost_price, low_stock_at, featured, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)')->execute([
                $p['category_id'], $p['name'], $slug, 'Description coming soon.', $d['price'], $d['stock'] ?? 0, 'Gold tone', 'img/logo-mark.svg', $d['cost_price'] ?? null, $d['low_stock_at'] ?? null,
            ]);
            $new++;
        }
    }
    cache_flush();
    log_activity('bulk_import', 'products', $upd . ' updated, ' . $new . ' added');
    return ['updated' => $upd, 'created' => $new, 'failed' => $failed];
}

function bulk_price_percent(?int $categoryId, float $percent, ?int $adminId): array
{
    if ($percent == 0.0 || $percent < -90 || $percent > 200) {
        return [false, 'Enter a percentage between -90 and 200, not 0.', 0];
    }
    $sql = 'UPDATE vb_products SET price = ROUND(price * (1 + ? / 100.0), 2)' . ($categoryId ? ' WHERE category_id = ?' : '');
    $st = db()->prepare($sql);
    $st->execute($categoryId ? [$percent, $categoryId] : [$percent]);
    cache_flush();
    log_activity('bulk_price', $categoryId ? 'category ' . $categoryId : 'all products', ($percent > 0 ? '+' : '') . $percent . '%');
    return [true, 'Prices changed by ' . ($percent > 0 ? '+' : '') . $percent . '% on ' . $st->rowCount() . ' product(s).', $st->rowCount()];
}

function bulk_grid_save(array $prices, array $stocks, ?int $adminId): array
{
    $cur = db()->query('SELECT id, name, price, stock FROM vb_products')->fetchAll(PDO::FETCH_UNIQUE);
    $changed = 0;
    $errors = [];
    foreach ($cur as $id => $p) {
        $id = (int) $id;
        if (isset($prices[$id]) && trim((string) $prices[$id]) !== '') {
            $v = str_replace(',', '', trim((string) $prices[$id]));
            if (!is_numeric($v) || (float) $v <= 0 || (float) $v > 1000000) {
                $errors[] = $p['name'] . ': price must be above 0.';
            } elseif (abs((float) $v - (float) $p['price']) >= 0.005) {
                db()->prepare('UPDATE vb_products SET price = ? WHERE id = ?')->execute([round((float) $v, 2), $id]);
                $changed++;
            }
        }
        if (isset($stocks[$id]) && trim((string) $stocks[$id]) !== '') {
            $v = trim((string) $stocks[$id]);
            if (!ctype_digit($v) || (int) $v > 100000) {
                $errors[] = $p['name'] . ': stock must be a whole number.';
            } elseif ((int) $v !== (int) $p['stock']) {
                $r = stock_adjust($id, (int) $v - (int) $p['stock'], 'adjust', 'Bulk edit', $adminId);
                if ($r[0]) {
                    $changed++;
                } else {
                    $errors[] = $p['name'] . ': ' . $r[1];
                }
            }
        }
    }
    cache_flush();
    log_activity('bulk_edit', 'products', $changed . ' change(s)');
    return [$changed, $errors];
}

function packing_orders(array $ids = [], string $status = ''): array
{
    if ($ids) {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, 100);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT id FROM vb_orders WHERE id IN ($in) AND kind = 'shop' ORDER BY id");
        $st->execute($ids);
    } else {
        $status = in_array($status, order_statuses(), true) ? $status : 'processing';
        $st = db()->prepare("SELECT id FROM vb_orders WHERE order_status = ? AND kind = 'shop' ORDER BY id LIMIT 100");
        $st->execute([$status]);
    }
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $oid) {
        $o = order_by_id((int) $oid);
        if ($o) {
            $o['items'] = order_items_for((int) $oid);
            $out[] = $o;
        }
    }
    return $out;
}
