<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$r = backup_all();
echo $r['db'][0] ? 'Database backup: ' . basename($r['db'][1]) . ' (' . $r['db'][2] . " rows)\n" : $r['db'][1] . "\n";
echo $r['images'][0] ? 'Images backup: ' . basename($r['images'][1]) . ' (' . $r['images'][2] . " files)\n" : $r['images'][1] . "\n";
echo 'Old backups removed: ' . $r['pruned'] . ', old log files removed: ' . log_prune() . "\n";
$copy = backup_copy_out();
if ($copy[0] !== null) {
    echo 'Copy off server: ' . $copy[1] . "\n";
}
$ok = $r['db'][0] && $r['images'][0] && $copy[0] !== false;
exit($ok ? 0 : 1);
