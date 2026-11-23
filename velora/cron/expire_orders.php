<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$n = expire_unpaid_orders(200);
echo $n . " unpaid order(s) cancelled.\n";
