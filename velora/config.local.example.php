<?php
return [
    'db' => [
        'pass' => 'YOUR_DATABASE_PASSWORD',
    ],
    'mail' => [
        'from' => 'orders@YOUR-DOMAIN.com',
        'brevo_key' => 'YOUR_BREVO_API_KEY',
    ],
    'alerts' => [
        'order_email' => 'you@YOUR-DOMAIN.com',
        'whatsapp_to' => '024 123 4567',
    ],
    'backup' => [
        'copy_dir' => '/home/YOUR_USER/backup_copies',
    ],
    'security' => [
        'trusted_proxy' => false,
        'cookie_path' => '/~YOUR_USER/ecommerce-class/velora/',
    ],
    'paystack' => [
        'public_key' => 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxx',
        'secret_key' => 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxx',
    ],
];
