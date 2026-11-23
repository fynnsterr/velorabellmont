<?php
return [
    'debug' => false,
    'db' => [
        'host' => 'localhost',
        'name' => 'ecommerce_2026A_nana_biney',
        'user' => 'nana.biney',
        'pass' => '',
        'charset' => 'utf8mb4',
        'sqlite_path' => getenv('VB_SQLITE') ?: null,
    ],
    'site' => [
        'name' => 'Velora Bellmont',
        'url' => 'http://169.239.251.102:442/~nana.biney/ecommerce-class/velora',
        'currency' => 'GHS',
        'symbol' => 'GH₵',
        'support_email' => 'bellmontvelora@gmail.com',
        'support_phone' => '+233 26 587 8413',
    ],
    'security' => [
        'trusted_proxy' => false,
        'cookie_path' => null,
    ],
    'promos' => [
        'once_per_customer' => ['WELCOME10'],
    ],
    'paystack' => [
        'public_key' => '',
        'secret_key' => '',
    ],
    'orders' => [
        'unpaid_hours' => 24,
        'cod_unconfirmed_hours' => 72,
        'cod_open_limit' => 2,
    ],
    'shipping' => [
        'flat' => 25.00,
        'free_over' => 500.00,
    ],
];
