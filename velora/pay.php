<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('account.php');
}
csrf_verify();

$order = user_order(val('ref'), $user);
if (!$order || $order['payment_method'] !== 'paystack' || $order['payment_status'] === 'paid' || $order['order_status'] === 'cancelled') {
    flash('error', 'That order cannot be paid for.');
    redirect('account.php');
}
start_payment($order);
