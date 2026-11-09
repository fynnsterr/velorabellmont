<?php
require __DIR__ . '/includes/bootstrap.php';

$ref = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$order = $ref !== '' ? find_order_by_ref($ref) : null;

if (!$order || !paystack_enabled()) {
    flash('error', 'We could not match that payment to an order. If you were charged, contact us with your payment receipt.');
    redirect('account.php');
}

if ($order['payment_status'] === 'paid' || paystack_verify_order($order)) {
    flash('success', 'Payment received. Thank you for your order.');
} else {
    db()->prepare("UPDATE vb_orders SET payment_status = 'failed' WHERE id = ? AND payment_status = 'pending'")->execute([(int) $order['id']]);
    flash('error', 'We could not confirm your payment. You can try again from your order.');
}
redirect('order.php?ref=' . urlencode($order['order_ref']));
