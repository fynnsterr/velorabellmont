<?php

function order_flow(): array
{
    return ['pending', 'processing', 'dispatched', 'out_for_delivery', 'delivered'];
}

function order_status_label(string $s): string
{
    $map = [
        'pending' => 'Order received',
        'processing' => 'Processing',
        'dispatched' => 'Dispatched',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'partially_refunded' => 'Part refunded',
        'refunded' => 'Refunded',
    ];
    return $map[$s] ?? ucfirst(str_replace('_', ' ', $s));
}

function order_is_paid(array $order): bool
{
    return in_array($order['payment_status'], ['paid', 'partially_refunded', 'refunded'], true);
}

function paid_statuses_sql(): string
{
    return "('paid', 'partially_refunded', 'refunded')";
}

function order_event(int $orderId, string $event, ?string $note = null, ?int $adminId = null): void
{
    db()->prepare('INSERT INTO vb_order_events (order_id, event, note, admin_id) VALUES (?, ?, ?, ?)')
        ->execute([$orderId, $event, $note !== null ? mb_substr($note, 0, 255) : null, $adminId]);
}

function order_events(int $orderId): array
{
    $st = db()->prepare('SELECT * FROM vb_order_events WHERE order_id = ? ORDER BY id');
    $st->execute([$orderId]);
    return $st->fetchAll();
}

function order_event_label(string $event): string
{
    $map = [
        'placed' => 'Order received',
        'paid' => 'Payment confirmed',
        'processing' => 'Processing started',
        'dispatched' => 'Dispatched',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'paid_after_cancel' => 'Paid after cancellation',
        'refund_recorded' => 'Refund recorded',
        'refund_paid' => 'Refund sent',
        'refund_rejected' => 'Refund rejected',
        'shipping_updated' => 'Shipping details updated',
    ];
    return $map[$event] ?? ucfirst(str_replace('_', ' ', $event));
}

function order_timeline(array $order, array $events): array
{
    $isCod = $order['payment_method'] === 'cod';
    $rank = ['pending' => 0, 'processing' => 1, 'dispatched' => 2, 'out_for_delivery' => 3, 'delivered' => 4];
    $r = $rank[$order['order_status']] ?? 0;
    $at = [];
    foreach ($events as $ev) {
        if (!isset($at[$ev['event']])) {
            $at[$ev['event']] = $ev['created_at'];
        }
    }
    $steps = [
        ['key' => 'placed', 'label' => 'Order received', 'done' => true, 'at' => $at['placed'] ?? $order['created_at']],
        ['key' => 'paid', 'label' => $isCod ? 'Pay on delivery' : 'Payment confirmed', 'done' => $isCod ? $r >= 1 : order_is_paid($order), 'at' => $isCod ? null : ($at['paid'] ?? $order['paid_at'] ?? null)],
        ['key' => 'processing', 'label' => 'Processing', 'done' => $r >= 1, 'at' => $at['processing'] ?? null],
        ['key' => 'dispatched', 'label' => 'Dispatched', 'done' => $r >= 2, 'at' => $at['dispatched'] ?? $order['dispatched_at'] ?? null],
        ['key' => 'out_for_delivery', 'label' => 'Out for delivery', 'done' => $r >= 3, 'at' => $at['out_for_delivery'] ?? null],
        ['key' => 'delivered', 'label' => 'Delivered', 'done' => $r >= 4, 'at' => $at['delivered'] ?? $order['delivered_at'] ?? null],
    ];
    $currentSet = false;
    foreach ($steps as $i => $s) {
        if ($s['done']) {
            $steps[$i]['state'] = 'done';
        } elseif (!$currentSet) {
            $steps[$i]['state'] = 'current';
            $currentSet = true;
        } else {
            $steps[$i]['state'] = 'todo';
        }
    }
    return $steps;
}

function order_transitions(array $order): array
{
    $s = $order['order_status'];
    if ($s === 'cancelled' || $s === 'delivered') {
        return [];
    }
    if ($order['kind'] !== 'shop') {
        return ['cancelled'];
    }
    $flow = order_flow();
    $i = array_search($s, $flow, true);
    $next = $i === false ? [] : array_slice($flow, $i + 1);
    $next[] = 'cancelled';
    return $next;
}

function order_set_status(int $orderId, string $new, array $opt = [], ?int $adminId = null): array
{
    $order = order_by_id($orderId);
    if (!$order) {
        return [false, 'That order does not exist.'];
    }
    if (!in_array($new, order_transitions($order), true)) {
        return [false, 'This order cannot move from ' . order_status_label($order['order_status']) . ' to ' . order_status_label($new) . '.'];
    }
    $isCod = $order['payment_method'] === 'cod';
    if (in_array($new, ['processing', 'dispatched', 'out_for_delivery', 'delivered'], true) && !$isCod && !order_is_paid($order)) {
        return [false, 'Confirm the payment before moving this order on.'];
    }
    $courier = trim((string) ($opt['courier'] ?? ''));
    $tracking = trim((string) ($opt['tracking_no'] ?? ''));
    $trackUrl = trim((string) ($opt['tracking_url'] ?? ''));
    if ($new === 'dispatched') {
        if ($courier === '') {
            return [false, 'Enter the courier before dispatching.'];
        }
        if (mb_strlen($courier) > 60 || mb_strlen($tracking) > 80) {
            return [false, 'The courier or tracking number is too long.'];
        }
        if ($trackUrl !== '' && (!preg_match('~^https?://~i', $trackUrl) || !filter_var($trackUrl, FILTER_VALIDATE_URL) || strlen($trackUrl) > 255)) {
            return [false, 'The tracking link must start with http:// or https://.'];
        }
    }
    $note = trim((string) ($opt['note'] ?? ''));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sets = ['order_status = ?'];
        $params = [$new];
        if ($new === 'dispatched') {
            array_push($sets, 'courier = ?', 'tracking_no = ?', 'tracking_url = ?', 'dispatched_at = COALESCE(dispatched_at, CURRENT_TIMESTAMP)');
            array_push($params, $courier, $tracking !== '' ? $tracking : null, $trackUrl !== '' ? $trackUrl : null);
        } elseif ($new === 'out_for_delivery') {
            $sets[] = 'dispatched_at = COALESCE(dispatched_at, CURRENT_TIMESTAMP)';
        } elseif ($new === 'delivered') {
            $sets[] = 'dispatched_at = COALESCE(dispatched_at, CURRENT_TIMESTAMP)';
            $sets[] = 'delivered_at = COALESCE(delivered_at, CURRENT_TIMESTAMP)';
        }
        $params[] = $orderId;
        $params[] = $order['order_status'];
        $up = $pdo->prepare('UPDATE vb_orders SET ' . implode(', ', $sets) . ' WHERE id = ? AND order_status = ?');
        $up->execute($params);
        if ($up->rowCount() < 1) {
            $pdo->rollBack();
            return [false, 'Someone else just changed this order. Reload and try again.'];
        }
        if ($new === 'cancelled') {
            restock_order($orderId, 'cancel');
        }
        order_event($orderId, $new, $note !== '' ? $note : null, $adminId);
        $pdo->commit();
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $t;
    }
    if ($new === 'cancelled') {
        reverse_order_rewards($order);
    }
    if ($new === 'delivered' && $isCod && !order_is_paid($order)) {
        mark_order_paid($orderId, 'COD-' . $order['order_ref']);
    }
    if ($new === 'delivered') {
        award_referral_points($orderId);
    }
    if ($new === 'cancelled') {
        stock_alerts_send();
    }
    if (($opt['notify'] ?? true) && in_array($new, ['dispatched', 'out_for_delivery', 'delivered', 'cancelled'], true)) {
        order_mail($orderId, $new);
    }
    return [true, 'Order moved to ' . order_status_label($new) . '.'];
}

function expire_unpaid_orders(int $limit = 50): int
{
    $hours = (int) cfg('orders.unpaid_hours', 24);
    if ($hours < 1) {
        return 0;
    }
    $cut = date('Y-m-d H:i:s', time() - $hours * 3600);
    $st = db()->prepare("SELECT id FROM vb_orders WHERE kind = 'shop' AND order_status = 'pending' AND payment_status = 'pending' AND payment_method <> 'cod' AND created_at < ? ORDER BY id LIMIT " . max(1, $limit));
    $st->execute([$cut]);
    $done = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
        [$ok] = order_set_status((int) $id, 'cancelled', ['note' => 'Not paid within ' . $hours . ' hours']);
        if ($ok) {
            $done++;
        }
    }
    $codHours = (int) cfg('orders.cod_unconfirmed_hours', 72);
    if ($codHours > 0) {
        $cut = date('Y-m-d H:i:s', time() - $codHours * 3600);
        $st = db()->prepare("SELECT id FROM vb_orders WHERE kind = 'shop' AND order_status = 'pending' AND payment_status = 'pending' AND payment_method = 'cod' AND created_at < ? ORDER BY id LIMIT " . max(1, $limit));
        $st->execute([$cut]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
            [$ok] = order_set_status((int) $id, 'cancelled', ['note' => 'Pay on delivery order not confirmed within ' . $codHours . ' hours']);
            if ($ok) {
                $done++;
            }
        }
    }
    return $done;
}

function order_update_shipping(int $orderId, string $courier, string $tracking, string $trackUrl, ?int $adminId = null): array
{
    $order = order_by_id($orderId);
    if (!$order || !in_array($order['order_status'], ['dispatched', 'out_for_delivery'], true)) {
        return [false, 'Shipping details can be changed once an order is dispatched and until it is delivered.'];
    }
    $courier = trim($courier);
    $tracking = trim($tracking);
    $trackUrl = trim($trackUrl);
    if ($courier === '' || mb_strlen($courier) > 60 || mb_strlen($tracking) > 80) {
        return [false, 'Enter the courier (up to 60 characters) and a tracking number up to 80 characters.'];
    }
    if ($trackUrl !== '' && (!preg_match('~^https?://~i', $trackUrl) || !filter_var($trackUrl, FILTER_VALIDATE_URL) || strlen($trackUrl) > 255)) {
        return [false, 'The tracking link must start with http:// or https://.'];
    }
    db()->prepare('UPDATE vb_orders SET courier = ?, tracking_no = ?, tracking_url = ? WHERE id = ?')
        ->execute([$courier, $tracking !== '' ? $tracking : null, $trackUrl !== '' ? $trackUrl : null, $orderId]);
    order_event($orderId, 'shipping_updated', $courier . ($tracking !== '' ? ' ' . $tracking : ''), $adminId);
    return [true, 'Shipping details saved.'];
}

function order_confirm_payment(int $orderId, string $reference, ?int $adminId = null): array
{
    $order = order_by_id($orderId);
    if (!$order || $order['kind'] !== 'shop') {
        return [false, 'That order cannot be marked as paid.'];
    }
    if ($order['order_status'] === 'cancelled') {
        return [false, 'This order was cancelled.'];
    }
    if (order_is_paid($order)) {
        return [false, 'This order is already paid.'];
    }
    $ref = trim($reference) !== '' ? mb_substr(trim($reference), 0, 80) : 'MANUAL-' . $order['order_ref'];
    mark_order_paid($orderId, $ref);
    if ($adminId !== null) {
        db()->prepare("UPDATE vb_order_events SET admin_id = ? WHERE id = (SELECT MAX(id) FROM vb_order_events WHERE order_id = ? AND event = 'paid')")->execute([$adminId, $orderId]);
    }
    return [true, 'Payment confirmed.'];
}

function refund_methods(): array
{
    return ['paystack' => 'Paystack dashboard', 'momo' => 'Mobile money', 'bank' => 'Bank transfer', 'cash' => 'Cash'];
}

function refund_reasons(): array
{
    return ['returned' => 'Item returned', 'faulty' => 'Faulty or damaged', 'cancelled' => 'Order cancelled', 'not_delivered' => 'Not delivered', 'other' => 'Other'];
}

function refund_badge(string $status): string
{
    $label = ['pending' => 'To send', 'paid' => 'Sent', 'rejected' => 'Rejected'][$status] ?? ucfirst($status);
    $class = ['pending' => 'pending', 'paid' => 'delivered', 'rejected' => 'cancelled'][$status] ?? 'pending';
    return '<span class="badge badge-' . e($class) . '">' . e($label) . '</span>';
}

function refunds_for_order(int $orderId): array
{
    $st = db()->prepare('SELECT * FROM vb_refunds WHERE order_id = ? ORDER BY id');
    $st->execute([$orderId]);
    return $st->fetchAll();
}

function order_refundable(array $order): float
{
    if ($order['kind'] !== 'shop' || !order_is_paid($order) || $order['payment_status'] === 'refunded') {
        return 0.0;
    }
    $st = db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM vb_refunds WHERE order_id = ? AND status <> 'rejected'");
    $st->execute([(int) $order['id']]);
    return max(0.0, round((float) $order['total'] - (float) $st->fetchColumn(), 2));
}

function refund_create(int $orderId, float $amount, string $reason, string $method, string $note, bool $restock, ?int $adminId = null): array
{
    $order = order_by_id($orderId);
    if (!$order || $order['kind'] !== 'shop') {
        return [false, 'That order cannot be refunded.'];
    }
    $left = order_refundable($order);
    $amount = round($amount, 2);
    if ($left <= 0) {
        return [false, 'There is nothing left to refund on this order.'];
    }
    if ($amount <= 0 || $amount > $left + 0.001) {
        return [false, 'Enter an amount between ' . money(0.01) . ' and ' . money($left) . '.'];
    }
    if (!isset(refund_reasons()[$reason]) || !isset(refund_methods()[$method])) {
        return [false, 'Choose the reason and how the money will be sent.'];
    }
    if (mb_strlen($note) > 255) {
        return [false, 'Keep the note under 255 characters.'];
    }
    if ($restock) {
        if (abs($amount - $left) >= 0.01) {
            return [false, 'Returning the items to stock only works with a refund of the full amount.'];
        }
        if (order_restocked($orderId)) {
            return [false, 'The items for this order are already back in stock.'];
        }
    }
    db()->prepare('INSERT INTO vb_refunds (order_id, amount, reason, method, note, restock, status, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$orderId, $amount, $reason, $method, $note !== '' ? $note : null, $restock ? 1 : 0, 'pending', $adminId]);
    $id = (int) db()->lastInsertId();
    order_event($orderId, 'refund_recorded', money($amount) . ' (' . refund_reasons()[$reason] . ')', $adminId);
    return [true, 'Refund recorded. Mark it as sent once the money has gone out.', $id];
}

function refund_set_status(int $refundId, string $status, string $reference = '', ?int $adminId = null): array
{
    if (!in_array($status, ['paid', 'rejected'], true)) {
        return [false, 'Choose sent or rejected.'];
    }
    $st = db()->prepare('SELECT * FROM vb_refunds WHERE id = ?');
    $st->execute([$refundId]);
    $r = $st->fetch();
    if (!$r) {
        return [false, 'That refund does not exist.'];
    }
    if ($r['status'] !== 'pending') {
        return [false, 'This refund is already ' . $r['status'] . '.'];
    }
    $up = db()->prepare("UPDATE vb_refunds SET status = ?, reference = ?, paid_at = " . ($status === 'paid' ? 'CURRENT_TIMESTAMP' : 'NULL') . " WHERE id = ? AND status = 'pending'");
    $up->execute([$status, $reference !== '' ? mb_substr($reference, 0, 80) : null, $refundId]);
    if ($up->rowCount() < 1) {
        return [false, 'This refund was just updated. Reload the page.'];
    }
    $order = order_by_id((int) $r['order_id']);
    if ($status === 'rejected') {
        order_event((int) $r['order_id'], 'refund_rejected', money((float) $r['amount']), $adminId);
        return [true, 'Refund rejected.'];
    }
    $sum = db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM vb_refunds WHERE order_id = ? AND status = 'paid'");
    $sum->execute([(int) $r['order_id']]);
    $paid = (float) $sum->fetchColumn();
    $full = $paid >= (float) $order['total'] - 0.005;
    db()->prepare('UPDATE vb_orders SET payment_status = ? WHERE id = ?')->execute([$full ? 'refunded' : 'partially_refunded', (int) $r['order_id']]);
    order_event((int) $r['order_id'], 'refund_paid', money((float) $r['amount']) . ' by ' . (refund_methods()[$r['method']] ?? $r['method']), $adminId);
    if ($full) {
        reverse_earned_points($order);
    }
    if ((int) $r['restock'] === 1 && !order_restocked((int) $r['order_id'])) {
        restock_order((int) $r['order_id'], 'return');
    }
    order_mail((int) $r['order_id'], 'refund', ['amount' => (float) $r['amount'], 'method' => strtolower(refund_methods()[$r['method']] ?? $r['method'])]);
    return [true, 'Refund marked as sent.'];
}

function stock_reasons(): array
{
    return ['sale' => 'Sale', 'cancel' => 'Order cancelled', 'return' => 'Customer return', 'restock' => 'Restock', 'damaged' => 'Damaged or lost', 'adjust' => 'Correction', 'edit' => 'Edited product'];
}

function stock_log(int $productId, int $delta, string $reason, ?int $orderId = null, ?string $note = null, ?int $adminId = null): void
{
    $st = db()->prepare('SELECT stock FROM vb_products WHERE id = ?');
    $st->execute([$productId]);
    $bal = (int) $st->fetchColumn();
    db()->prepare('INSERT INTO vb_stock_moves (product_id, qty_change, balance, reason, note, order_id, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$productId, $delta, $bal, $reason, $note !== null && $note !== '' ? mb_substr($note, 0, 160) : null, $orderId, $adminId]);
}

function stock_moves_for(int $productId, int $limit = 50): array
{
    $st = db()->prepare('SELECT m.*, o.order_ref FROM vb_stock_moves m LEFT JOIN vb_orders o ON o.id = m.order_id WHERE m.product_id = ? ORDER BY m.id DESC LIMIT ' . (int) $limit);
    $st->execute([$productId]);
    return $st->fetchAll();
}

function stock_adjust(int $productId, int $delta, string $reason, string $note = '', ?int $adminId = null): array
{
    if ($delta === 0) {
        return [false, 'Enter a quantity above 0.'];
    }
    if (!isset(stock_reasons()[$reason])) {
        return [false, 'Choose a reason.'];
    }
    $up = db()->prepare('UPDATE vb_products SET stock = stock + ? WHERE id = ? AND stock + ? >= 0');
    $up->execute([$delta, $productId, $delta]);
    if ($up->rowCount() < 1) {
        return [false, 'You cannot remove more than the stock on hand.'];
    }
    stock_log($productId, $delta, $reason, null, $note, $adminId);
    stock_alert_check($productId);
    stock_alerts_send($productId);
    return [true, 'Stock updated.'];
}

function order_restocked(int $orderId): bool
{
    $st = db()->prepare("SELECT COUNT(*) FROM vb_stock_moves WHERE order_id = ? AND reason IN ('cancel', 'return')");
    $st->execute([$orderId]);
    return (int) $st->fetchColumn() > 0;
}

function restock_order(int $orderId, string $reason): void
{
    if (order_restocked($orderId)) {
        return;
    }
    $st = db()->prepare('SELECT product_id, quantity FROM vb_order_items WHERE order_id = ? AND product_id IS NOT NULL');
    $st->execute([$orderId]);
    $up = db()->prepare('UPDATE vb_products SET stock = stock + ? WHERE id = ?');
    foreach ($st->fetchAll() as $row) {
        $up->execute([(int) $row['quantity'], (int) $row['product_id']]);
        stock_log((int) $row['product_id'], (int) $row['quantity'], $reason, $orderId);
        stock_alert_check((int) $row['product_id']);
    }
}

function low_stock_level(array $p): int
{
    return isset($p['low_stock_at']) && $p['low_stock_at'] !== '' && $p['low_stock_at'] !== null ? (int) $p['low_stock_at'] : (int) cfg('stock.low_at', 5);
}

function low_stock_products(int $limit = 0): array
{
    $sql = 'SELECT p.*, c.name AS category_name FROM vb_products p JOIN vb_categories c ON c.id = p.category_id WHERE p.active = 1 AND p.stock <= COALESCE(p.low_stock_at, ?) ORDER BY p.stock, p.name' . ($limit > 0 ? ' LIMIT ' . (int) $limit : '');
    $st = db()->prepare($sql);
    $st->execute([(int) cfg('stock.low_at', 5)]);
    return $st->fetchAll();
}

function stock_alert_check(int $productId): void
{
    $st = db()->prepare('SELECT id, name, stock, low_stock_at, low_alerted, active FROM vb_products WHERE id = ?');
    $st->execute([$productId]);
    $p = $st->fetch();
    if (!$p) {
        return;
    }
    if ((int) $p['stock'] <= low_stock_level($p)) {
        if ((int) $p['low_alerted'] === 0 && (int) $p['active'] === 1) {
            $claim = db()->prepare('UPDATE vb_products SET low_alerted = 1 WHERE id = ? AND low_alerted = 0');
            $claim->execute([$productId]);
            if ($claim->rowCount() > 0) {
                $left = (int) $p['stock'];
                $text = $p['name'] . ($left === 0 ? ' has sold out.' : ' is running low. ' . $left . ' left (alert level ' . low_stock_level($p) . ').')
                    . "\n\nAdd stock here: " . abs_url('admin/stock.php?id=' . (int) $p['id']);
                $to = (string) (cfg('stock.alert_email', '') ?: cfg('site.support_email'));
                send_mail($to, ($left === 0 ? 'Sold out: ' : 'Low stock: ') . $p['name'], $text, ['type' => 'low_stock']);
            }
        }
    } elseif ((int) $p['low_alerted'] === 1) {
        db()->prepare('UPDATE vb_products SET low_alerted = 0 WHERE id = ?')->execute([$productId]);
    }
}

function admin_attention(): array
{
    $one = function (string $sql, array $params = []): int {
        $st = db()->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    };
    return [
        'to_process' => $one("SELECT COUNT(*) FROM vb_orders WHERE kind = 'shop' AND order_status = 'pending' AND (payment_status = 'paid' OR payment_method = 'cod')"),
        'to_dispatch' => $one("SELECT COUNT(*) FROM vb_orders WHERE kind = 'shop' AND order_status = 'processing'"),
        'on_the_road' => $one("SELECT COUNT(*) FROM vb_orders WHERE kind = 'shop' AND order_status IN ('dispatched', 'out_for_delivery')"),
        'awaiting_payment' => $one("SELECT COUNT(*) FROM vb_orders WHERE kind = 'shop' AND order_status = 'pending' AND payment_method <> 'cod' AND payment_status <> 'paid'"),
        'refunds_pending' => $one("SELECT COUNT(*) FROM vb_refunds WHERE status = 'pending'"),
        'low_stock' => count(low_stock_products()),
    ];
}

function sale_scope_ids(string $scope, int $id): array
{
    if ($scope === 'category') {
        $st = db()->prepare('SELECT id FROM vb_products WHERE active = 1 AND category_id = ?');
        $st->execute([$id]);
    } elseif ($scope === 'collection') {
        $st = db()->prepare('SELECT p.id FROM vb_products p JOIN vb_collection_items i ON i.product_id = p.id WHERE p.active = 1 AND i.collection_id = ?');
        $st->execute([$id]);
    } else {
        $st = db()->query('SELECT id FROM vb_products WHERE active = 1');
    }
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function bulk_sale_apply(string $scope, int $id, float $percent, string $ends): array
{
    if ($percent < 5 || $percent > 90) {
        return [false, 'Enter a percentage between 5 and 90.'];
    }
    if ($ends !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ends) || $ends < date('Y-m-d'))) {
        return [false, 'Use an end date in the format 2026-12-31 that has not passed, or leave it empty.'];
    }
    $ids = sale_scope_ids($scope, $id);
    if (!$ids) {
        return [false, 'No active products match that choice.'];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $up = db()->prepare('UPDATE vb_products SET sale_price = ROUND(price * (1 - ? / 100.0), 2), sale_ends = ? WHERE id IN (' . $in . ')');
    $up->execute(array_merge([$percent, $ends !== '' ? $ends : null], $ids));
    return [true, count($ids) . ' products are now ' . rtrim(rtrim(number_format($percent, 1), '0'), '.') . '% off' . ($ends !== '' ? ' until ' . $ends : '') . '.'];
}

function bulk_sale_clear(string $scope, int $id): array
{
    $ids = sale_scope_ids($scope, $id);
    if (!$ids) {
        return [false, 'No active products match that choice.'];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    db()->prepare('UPDATE vb_products SET sale_price = NULL, sale_ends = NULL WHERE id IN (' . $in . ')')->execute($ids);
    return [true, 'Sale prices removed from ' . count($ids) . ' products.'];
}

function active_sale_count(): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM vb_products WHERE active = 1 AND sale_price IS NOT NULL AND sale_price < price AND (sale_ends IS NULL OR sale_ends >= ?)');
    $st->execute([date('Y-m-d')]);
    return (int) $st->fetchColumn();
}

function stock_alert_subscribe(int $productId, string $email): string
{
    $email = strtolower(trim($email));
    if ($email === '' || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'invalid';
    }
    $p = db()->prepare('SELECT stock FROM vb_products WHERE id = ? AND active = 1');
    $p->execute([$productId]);
    $stock = $p->fetchColumn();
    if ($stock === false) {
        return 'invalid';
    }
    if ((int) $stock > 0) {
        return 'in_stock';
    }
    $st = db()->prepare('SELECT id, notified_at FROM vb_stock_alerts WHERE product_id = ? AND email = ?');
    $st->execute([$productId, $email]);
    $row = $st->fetch();
    if ($row) {
        if ($row['notified_at'] === null) {
            return 'exists';
        }
        db()->prepare('UPDATE vb_stock_alerts SET notified_at = NULL, created_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $row['id']]);
        return 'added';
    }
    db()->prepare('INSERT INTO vb_stock_alerts (product_id, email) VALUES (?, ?)')->execute([$productId, $email]);
    return 'added';
}

function stock_alerts_send(int $productId = 0, ?callable $sender = null, int $limit = 200): int
{
    $sender = $sender ?? 'send_mail';
    $sql = 'SELECT a.id, a.email, p.name, p.slug, p.stock FROM vb_stock_alerts a JOIN vb_products p ON p.id = a.product_id WHERE a.notified_at IS NULL AND p.active = 1 AND p.stock > 0'
        . ($productId > 0 ? ' AND a.product_id = ' . (int) $productId : '') . ' ORDER BY a.id LIMIT ' . max(1, $limit);
    $sent = 0;
    foreach (db()->query($sql)->fetchAll() as $row) {
        $claim = db()->prepare('UPDATE vb_stock_alerts SET notified_at = CURRENT_TIMESTAMP WHERE id = ? AND notified_at IS NULL');
        $claim->execute([(int) $row['id']]);
        if ($claim->rowCount() < 1) {
            continue;
        }
        $link = abs_url('product.php?slug=' . urlencode($row['slug']));
        $left = (int) $row['stock'];
        $text = "Good news.\n\n" . $row['name'] . ' is back in stock' . ($left <= 5 ? ', with ' . $left . ' left' : '') . ".\n\nThis is a one time alert. Pieces can sell out again, so order soon.\n\n" . $link;
        $ok = (bool) $sender($row['email'], $row['name'] . ' is back in stock', $text, ['type' => 'stock_alert', 'button' => ['View the piece', $link]]);
        if ($ok) {
            $sent++;
        } else {
            db()->prepare('UPDATE vb_stock_alerts SET notified_at = NULL WHERE id = ?')->execute([(int) $row['id']]);
        }
    }
    return $sent;
}
