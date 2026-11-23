<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($user ? 'account.php' : 'track.php');
}
csrf_verify();

$order = user_order(val('ref'), $user);
if (!$order || $order['payment_method'] !== 'paystack' || order_is_paid($order) || $order['order_status'] === 'cancelled') {
    flash('error', 'That order cannot be paid for.');
    redirect($user ? 'account.php' : 'track.php');
}
start_payment($order);
