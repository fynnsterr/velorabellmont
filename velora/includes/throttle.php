<?php

function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (cfg('security.trusted_proxy', false)) {
        $fwd = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
        if ($fwd !== '' && filter_var($fwd, FILTER_VALIDATE_IP)) {
            $ip = $fwd;
        }
    }
    return $ip !== '' ? $ip : 'unknown';
}

function throttle_ready(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    db()->exec('CREATE TABLE IF NOT EXISTS vb_throttle (k VARCHAR(64) NOT NULL PRIMARY KEY, hits INT NOT NULL DEFAULT 0, reset_at INT NOT NULL)');
    $done = true;
}

function throttle_key(string $key): string
{
    return sha1($key);
}

function throttle_blocked(string $key, int $max): bool
{
    try {
        throttle_ready();
        $st = db()->prepare('SELECT hits, reset_at FROM vb_throttle WHERE k = ?');
        $st->execute([throttle_key($key)]);
        $row = $st->fetch();
        return $row && (int) $row['reset_at'] > time() && (int) $row['hits'] >= $max;
    } catch (Throwable $t) {
        vb_log('warning', 'throttle: ' . $t->getMessage());
        return false;
    }
}

function throttle_hit(string $key, int $max, int $window): bool
{
    try {
        throttle_ready();
        $k = throttle_key($key);
        $now = time();
        $st = db()->prepare('SELECT hits, reset_at FROM vb_throttle WHERE k = ?');
        $st->execute([$k]);
        $row = $st->fetch();
        if (!$row || (int) $row['reset_at'] <= $now) {
            db()->prepare('DELETE FROM vb_throttle WHERE k = ?')->execute([$k]);
            db()->prepare('INSERT INTO vb_throttle (k, hits, reset_at) VALUES (?, 1, ?)')->execute([$k, $now + $window]);
            if (random_int(1, 50) === 1) {
                db()->prepare('DELETE FROM vb_throttle WHERE reset_at < ?')->execute([$now - 3600]);
            }
            return 1 <= $max;
        }
        db()->prepare('UPDATE vb_throttle SET hits = hits + 1 WHERE k = ?')->execute([$k]);
        return (int) $row['hits'] + 1 <= $max;
    } catch (Throwable $t) {
        vb_log('warning', 'throttle: ' . $t->getMessage());
        return true;
    }
}

function throttle_clear(string $key): void
{
    try {
        throttle_ready();
        db()->prepare('DELETE FROM vb_throttle WHERE k = ?')->execute([throttle_key($key)]);
    } catch (Throwable $t) {
    }
}
