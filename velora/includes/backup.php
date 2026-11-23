<?php

function backup_dir(): string
{
    return storage_dir('backups', (string) cfg('backup.dir', ''));
}

function backup_driver(): string
{
    return db()->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function backup_tables(): array
{
    if (backup_driver() === 'sqlite') {
        $rows = db()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE 'vb\\_%' ESCAPE '\\' ORDER BY name")->fetchAll();
        return array_map(fn ($r) => (string) $r['name'], $rows);
    }
    $rows = db()->query("SHOW TABLES LIKE 'vb\\_%'")->fetchAll(PDO::FETCH_NUM);
    return array_map(fn ($r) => (string) $r[0], $rows);
}

function backup_value($v): string
{
    if ($v === null) {
        return 'NULL';
    }
    if (is_int($v)) {
        return (string) $v;
    }
    return db()->quote((string) $v);
}

function backup_write(string $path, callable $emit): void
{
    $fh = gzopen($path, 'wb6');
    if (!$fh) {
        throw new RuntimeException('Cannot write ' . basename($path));
    }
    $emit(function (string $stmt) use ($fh) {
        gzwrite($fh, $stmt . ";\n-- end\n");
    });
    gzclose($fh);
}

function backup_database(): array
{
    $dir = backup_dir();
    $path = $dir . '/db-' . date('Ymd-His') . '.sql.gz';
    $n = 0;
    try {
        $sqlite = backup_driver() === 'sqlite';
        backup_write($path, function (callable $w) use ($sqlite, &$n) {
            $w($sqlite ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS = 0');
            foreach (backup_tables() as $t) {
                $w('DROP TABLE IF EXISTS ' . $t);
                if ($sqlite) {
                    $w((string) db()->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . db()->quote($t))->fetchColumn());
                } else {
                    $w((string) db()->query('SHOW CREATE TABLE ' . $t)->fetch(PDO::FETCH_NUM)[1]);
                }
                $cols = null;
                $batch = [];
                $flush = function () use (&$batch, &$cols, $t, $w) {
                    if ($batch) {
                        $w('INSERT INTO ' . $t . ' (' . $cols . ') VALUES ' . implode(',', $batch));
                        $batch = [];
                    }
                };
                $st = db()->query('SELECT * FROM ' . $t);
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $cols = $cols ?? implode(', ', array_keys($row));
                    $batch[] = '(' . implode(', ', array_map('backup_value', array_values($row))) . ')';
                    $n++;
                    if (count($batch) >= 100) {
                        $flush();
                    }
                }
                $flush();
                if ($sqlite) {
                    $ix = db()->query("SELECT sql FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL AND tbl_name = " . db()->quote($t))->fetchAll();
                    foreach ($ix as $i) {
                        $w((string) $i['sql']);
                    }
                }
            }
            $w($sqlite ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS = 1');
        });
    } catch (Throwable $t) {
        @unlink($path);
        vb_log('error', 'Backup failed: ' . $t->getMessage());
        return [false, 'The database backup failed: ' . $t->getMessage()];
    }
    return [true, $path, $n];
}

function backup_restore_into(PDO $pdo, string $gzPath): int
{
    $raw = '';
    $fh = gzopen($gzPath, 'rb');
    while ($fh && !gzeof($fh)) {
        $raw .= gzread($fh, 65536);
    }
    if ($fh) {
        gzclose($fh);
    }
    $n = 0;
    foreach (explode("\n-- end\n", $raw) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $pdo->exec($stmt);
            $n++;
        }
    }
    return $n;
}

function backup_images(): array
{
    $path = backup_dir() . '/images-' . date('Ymd-His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return [false, 'Could not create the images backup.'];
    }
    $count = 0;
    foreach (['assets/uploads', 'assets/img/products', 'assets/img/packaging'] as $rel) {
        $base = VB_ROOT . '/' . $rel;
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && !in_array($f->getFilename(), ['.htaccess', '.gitkeep'], true) && strpos(str_replace('\\', '/', $f->getPathname()), '/webp/') === false) {
                $zip->addFile($f->getPathname(), $rel . '/' . substr($f->getPathname(), strlen($base) + 1));
                $count++;
            }
        }
    }
    if ($count === 0) {
        $zip->addFromString('README.txt', 'No images yet.');
    }
    $zip->close();
    return [true, $path, $count];
}

function backup_list(): array
{
    $out = [];
    foreach (glob(backup_dir() . '/{db-*.sql.gz,images-*.zip}', GLOB_BRACE) ?: [] as $f) {
        $out[] = ['name' => basename($f), 'type' => strpos(basename($f), 'db-') === 0 ? 'Database' : 'Images', 'size' => (int) filesize($f), 'time' => (int) filemtime($f)];
    }
    usort($out, fn ($a, $b) => $b['time'] <=> $a['time'] ?: strcmp($b['name'], $a['name']));
    return $out;
}

function backup_prune(?int $keep = null): int
{
    $keep = max(1, $keep ?? (int) cfg('backup.keep', 14));
    $n = 0;
    foreach (['Database', 'Images'] as $type) {
        $files = array_values(array_filter(backup_list(), fn ($b) => $b['type'] === $type));
        foreach (array_slice($files, $keep) as $b) {
            if (@unlink(backup_dir() . '/' . $b['name'])) {
                $n++;
            }
        }
    }
    return $n;
}

function backup_all(): array
{
    $db = backup_database();
    $img = backup_images();
    $pruned = backup_prune();
    return ['db' => $db, 'images' => $img, 'pruned' => $pruned];
}

function backup_path(string $name): ?string
{
    if (!preg_match('/^(db-\d{8}-\d{6}\.sql\.gz|images-\d{8}-\d{6}\.zip)$/', $name)) {
        return null;
    }
    $p = backup_dir() . '/' . $name;
    return is_file($p) ? $p : null;
}

