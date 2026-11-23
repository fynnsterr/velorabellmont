<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$scratch = $argv[1] ?? '';
if ($scratch === '') {
    echo "Usage: php cron/restore_test.php SCRATCH_DATABASE_NAME\n";
    echo "Create an empty spare database first (for example velora_restore_test) and give your database user access to it.\n";
    echo "This restores the newest backup into it and compares row counts with the live database. The live database is never changed.\n";
    exit(2);
}
$sqlite = cfg('db.sqlite_path');
if ($sqlite ? realpath($scratch) === realpath((string) $sqlite) : $scratch === cfg('db.name')) {
    echo "Refusing to use the live database as the scratch database.\n";
    exit(2);
}
$latest = null;
foreach (backup_list() as $b) {
    if ($b['type'] === 'Database') {
        $latest = backup_path((string) $b['name']);
        break;
    }
}
if ($latest === null) {
    echo "No database backup found. Run php cron/backup.php first.\n";
    exit(1);
}
$pdo = $sqlite
    ? new PDO('sqlite:' . $scratch)
    : new PDO('mysql:host=' . cfg('db.host') . ';dbname=' . $scratch . ';charset=' . cfg('db.charset', 'utf8mb4'), cfg('db.user'), cfg('db.pass'));
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (!$sqlite) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
}
$n = backup_restore_into($pdo, $latest);
echo 'Restored ' . basename($latest) . ' into ' . $scratch . ' (' . $n . " statements)\n";
$bad = 0;
foreach (backup_tables() as $t) {
    if (in_array($t, ['vb_activity', 'vb_mail_log', 'vb_product_views', 'vb_site_stats'], true)) {
        continue;
    }
    $live = (int) db()->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    try {
        $copy = (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    } catch (Throwable $e) {
        $copy = -1;
    }
    $ok = $live === $copy;
    $bad += $ok ? 0 : 1;
    echo ($ok ? 'OK   ' : 'DIFF ') . str_pad($t, 24) . ' live ' . $live . ', restored ' . $copy . "\n";
}
echo $bad === 0 ? "Restore test passed. Every table matches.\n" : $bad . " table(s) differ. Backups taken after the last backup will differ; run backup again and retest.\n";
exit($bad === 0 ? 0 : 1);
