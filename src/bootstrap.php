<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$dataDir = getenv('APP_DATA_DIR');
if (!is_string($dataDir) || $dataDir === '') {
    $dataDir = APP_ROOT . '/data';
}
define('APP_DATA_DIR', rtrim($dataDir, "/\\"));

const APP_NAME = 'Cinder';
const NOTE_MAX_BYTES = 32768;
const PASSWORD_MIN = 10;
const PASSWORD_MAX = 1024;
const NOTE_TTLS = [3600 => '1 hour', 86400 => '1 day', 604800 => '7 days', 2592000 => '30 days'];
const ARGON_OPS = 3;
const ARGON_MEM = 33554432;
const FAIL_LIMIT = 5;
const LOCK_SECONDS = 900;
const RATE_WINDOW = 600;
const RATE_CREATE_MAX = 20;
const RATE_REVEAL_MAX = 20;
const REQUEST_MAX_BYTES = 65536;

date_default_timezone_set('UTC');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf(
        'Unhandled %s: %s in %s:%d',
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; font-src 'self'");
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Error</title><link rel="stylesheet" href="/assets/app.css"></head>'
        . '<body><main><div class="wrap"><div class="narrow">'
        . '<p class="err-code">Error 500</p><h1 class="err-title">Something went wrong.</h1>'
        . '<p class="sub">The request could not be completed. Nothing was written to storage.</p>'
        . '<div class="actions"><a class="btn btn-outline" href="/">Back to Cinder</a></div>'
        . '</div></div></main></body></html>';
});

if (!is_dir(APP_DATA_DIR) && !@mkdir(APP_DATA_DIR, 0700, true) && !is_dir(APP_DATA_DIR)) {
    throw new RuntimeException('Data directory is not writable.');
}
@chmod(APP_DATA_DIR, 0700);
ini_set('error_log', APP_DATA_DIR . '/php-error.log');

function is_https(): bool
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
        || getenv('APP_HTTPS') === '1';
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function security_headers(string $contentType = 'text/html; charset=utf-8'): void
{
    if (headers_sent()) {
        return;
    }
    header_remove('X-Powered-By');
    header('Content-Type: ' . $contentType);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header(
        "Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; "
        . "img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; "
        . "base-uri 'none'; frame-ancestors 'none'; object-src 'none'"
    );
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path, int $status = 303): never
{
    header('Location: ' . $path, true, $status);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || strlen($_SESSION['csrf']) !== 64) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf'])
        && is_string($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

function request_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
}

function app_secret(): string
{
    static $secret = null;
    if (is_string($secret)) {
        return $secret;
    }
    $path = APP_DATA_DIR . '/app.key';
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || strlen($raw) !== 32) {
            throw new RuntimeException('Invalid application key.');
        }
        return $secret = $raw;
    }
    $raw = random_bytes(32);
    $handle = @fopen($path, 'xb');
    if ($handle === false) {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || strlen($raw) !== 32) {
            throw new RuntimeException('Could not create application key.');
        }
        return $secret = $raw;
    }
    try {
        if (fwrite($handle, $raw) !== 32) {
            throw new RuntimeException('Could not write application key.');
        }
    } finally {
        fclose($handle);
    }
    @chmod($path, 0600);
    return $secret = $raw;
}

function ip_bucket(string $kind): string
{
    return hash_hmac('sha256', $kind . "\0" . request_ip(), app_secret());
}

function valid_note_id(string $id): bool
{
    return preg_match('/\A[A-Za-z0-9_-]{16}\z/', $id) === 1;
}

function php_runtime_patched(): bool
{
    $minimum = match (PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) {
        '8.2' => 80235,
        '8.3' => 80336,
        '8.4' => 80427,
        '8.5' => 80512,
        default => (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION > 5) ? 0 : PHP_INT_MAX,
    };
    return PHP_VERSION_ID >= $minimum;
}

function required_extensions_loaded(): bool
{
    foreach (['pdo_sqlite', 'sodium', 'mbstring'] as $extension) {
        if (!extension_loaded($extension)) {
            error_log('Missing required PHP extension: ' . $extension);
            return false;
        }
    }
    return true;
}
