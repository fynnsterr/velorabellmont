<?php
declare(strict_types=1);

function paystack_enabled(): bool
{
    return trim((string) cfg('paystack.secret_key', '')) !== '';
}

function paystack_amount(float $total): int
{
    return (int) round($total * 100);
}

function paystack_request(string $method, string $path, ?array $body = null): ?array
{
    $ch = curl_init('https://api.paystack.co' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . cfg('paystack.secret_key'),
            'Content-Type: application/json',
        ],
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) {
        return null;
    }
    $json = json_decode($res, true);
    return is_array($json) ? $json : null;
}

function paystack_initialize(array $order): ?string
{
    $res = paystack_request('POST', '/transaction/initialize', [
        'email' => $order['email'],
        'amount' => paystack_amount((float) $order['total']),
        'currency' => cfg('site.currency', 'GHS'),
        'reference' => $order['order_ref'],
        'callback_url' => abs_url('paystack_callback.php'),
        'metadata' => ['order_id' => (int) $order['id'], 'customer' => $order['full_name']],
    ]);
    if ($res && !empty($res['status']) && !empty($res['data']['authorization_url'])) {
        return (string) $res['data']['authorization_url'];
    }
    return null;
}

function paystack_verify_order(array $order): bool
{
    $res = paystack_request('GET', '/transaction/verify/' . rawurlencode($order['order_ref']));
    if (!$res || empty($res['status']) || empty($res['data'])) {
        return false;
    }
    $data = $res['data'];
    if (($data['status'] ?? '') !== 'success') {
        return false;
    }
    if ((int) ($data['amount'] ?? 0) !== paystack_amount((float) $order['total'])) {
        return false;
    }
    if (strtoupper((string) ($data['currency'] ?? '')) !== strtoupper((string) cfg('site.currency', 'GHS'))) {
        return false;
    }
    mark_order_paid((int) $order['id'], (string) ($data['reference'] ?? $order['order_ref']));
    return true;
}

function find_order_by_ref(string $ref): ?array
{
    $st = db()->prepare('SELECT * FROM vb_orders WHERE order_ref = ?');
    $st->execute([$ref]);
    return $st->fetch() ?: null;
}

function start_payment(array $order): void
{
    if (!paystack_enabled()) {
        redirect('pay_demo.php?ref=' . urlencode($order['order_ref']));
    }
    $auth = paystack_initialize($order);
    if ($auth) {
        redirect($auth);
    }
    flash('error', 'We could not open the payment page. Your order is saved, so you can try again from here.');
    redirect('order.php?ref=' . urlencode($order['order_ref']));
}

function user_order(string $ref, array $user): ?array
{
    $order = find_order_by_ref($ref);
    if (!$order) {
        return null;
    }
    if ($user['role'] !== 'admin' && (int) $order['user_id'] !== (int) $user['id']) {
        return null;
    }
    return $order;
}
