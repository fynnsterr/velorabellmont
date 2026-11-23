<?php
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$q = trim(mb_substr(str_in($_GET, 'q'), 0, 60));
echo json_encode(search_suggest($q), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
