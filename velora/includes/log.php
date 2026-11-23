<?php

function storage_dir(string $kind, string $configured = ''): string
{
    $dir = $configured !== '' ? rtrim($configured, '/\\') : VB_ROOT . '/storage/' . $kind;
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (is_dir($dir) && !is_file($dir . '/.htaccess') && strpos(str_replace('\\', '/', $dir), str_replace('\\', '/', VB_ROOT)) === 0) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function log_dir(): string
{
    return storage_dir('logs', (string) cfg('log.dir', ''));
}

function log_file(?string $month = null): string
{
    return log_dir() . '/app-' . ($month ?? date('Y-m')) . '.log';
}

function log_reference(): string
{
    static $ref = null;
    if ($ref === null) {
        $ref = strtoupper(bin2hex(random_bytes(3)));
    }
    return $ref;
}

function vb_log(string $level, string $message, array $context = []): string
{
    $ref = log_reference();
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $line = json_encode([
        'time' => date('c'),
        'level' => $level,
        'ref' => $ref,
        'message' => mb_substr($message, 0, 2000),
        'path' => $path !== '' ? $path : (PHP_SAPI === 'cli' ? 'cli' : ''),
        'context' => $context,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    if (@file_put_contents(log_file(), $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('[velora ' . $level . ' ' . $ref . '] ' . $message);
    }
    return $ref;
}

function log_prune(int $keepMonths = 6): int
{
    $n = 0;
    $cut = date('Y-m', strtotime('-' . $keepMonths . ' months'));
    foreach (glob(log_dir() . '/app-*.log') ?: [] as $f) {
        if (preg_match('/app-(\d{4}-\d{2})\.log$/', $f, $m) && $m[1] < $cut && @unlink($f)) {
            $n++;
        }
    }
    return $n;
}

function log_install_handlers(): void
{
    $debug = (bool) cfg('debug', false);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
    set_error_handler(function (int $no, string $str, string $file, int $line): bool {
        if (!(error_reporting() & $no)) {
            return false;
        }
        $level = in_array($no, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true) ? 'warning' : (in_array($no, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true) ? 'notice' : 'error');
        vb_log($level, $str, ['file' => basename($file), 'line' => $line]);
        return !(bool) cfg('debug', false);
    });
    register_shutdown_function(function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            vb_log('fatal', $err['message'], ['file' => basename($err['file']), 'line' => $err['line']]);
        }
    });
}
