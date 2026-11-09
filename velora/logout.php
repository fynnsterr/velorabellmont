<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    logout_user();
    session_start();
    flash('success', 'You are logged out.');
}
redirect('index.php');
