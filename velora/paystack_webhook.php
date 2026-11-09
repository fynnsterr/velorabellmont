<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !paystack_enabled()) {
    http_response_code(405);
    exit;
}

$body = file_get_contents('php://input');
$sent = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$expected = hash_hmac('sha512', (string) $body, (string) cfg('paystack.secret_key'));

if (!hash_equals($expected, (string) $sent)) {
    http_response_code(401);
    exit;
}

$event = json_decode((string) $body, true);
if (is_array($event) && ($event['event'] ?? '') === 'charge.success') {
    $ref = (string) ($event['data']['reference'] ?? '');
    $order = $ref !== '' ? find_order_by_ref($ref) : null;
    if ($order && $order['payment_status'] !== 'paid') {
        paystack_verify_order($order);
    }
}
http_response_code(200);
