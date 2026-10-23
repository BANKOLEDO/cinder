<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/store.php';
require __DIR__ . '/../src/views.php';

security_headers();
start_session();

if (!php_runtime_patched()) {
    view_error(
        503,
        'Runtime needs a patch',
        'This host runs a PHP build affected by the PHP2Shell RCE flaws '
        . '(CVE-2026-109618 and CVE-2026-109619). Upgrade to PHP 8.4.27, 8.3.36, '
        . '8.5.12, or 8.2.35 before serving notes.'
    );
    exit;
}

if (!required_extensions_loaded()) {
    view_error(503, 'Service unavailable', 'The server is missing a required PHP extension.');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > REQUEST_MAX_BYTES
) {
    view_error(413, 'Payload too large', 'The request body exceeds the limit for a note.');
    exit;
}

function handle_create(): void
{
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        view_error(403, 'Request rejected', 'Your session expired or the form was tampered with. Reload and try again.');
        return;
    }

    $body = $_POST['body'] ?? '';
    $password = $_POST['password'] ?? '';
    $ttlRaw = $_POST['ttl'] ?? '';

    if (!is_string($body) || !is_string($password) || !is_string($ttlRaw)
        || preg_match('/\A\d+\z/', $ttlRaw) !== 1
        || !isset(NOTE_TTLS[(int) $ttlRaw])
    ) {
        view_error(422, 'Check the form', 'Choose a valid expiry and try again.');
        return;
    }

    $ttl = (int) $ttlRaw;

    if (trim($body) === '') {
        view_error(422, 'Nothing to seal', 'Write something in the note first.');
        return;
    }
    if (strlen($body) > NOTE_MAX_BYTES) {
        view_error(413, 'Note too large', 'Notes are limited to ' . NOTE_MAX_BYTES . ' bytes.');
        return;
    }
    if (strlen($password) < PASSWORD_MIN || strlen($password) > PASSWORD_MAX) {
        view_error(422, 'Password too short', 'Use at least ' . PASSWORD_MIN . ' characters.');
        return;
    }

    if (!rate_limit('create', RATE_CREATE_MAX)) {
        view_error(429, 'Slow down', 'Too many notes from this address. Try again shortly.', RATE_WINDOW);
        return;
    }

    gc_notes();
    $id = create_note($body, $password, $ttl);
    $_SESSION['created'] = is_array($_SESSION['created'] ?? null) ? $_SESSION['created'] : [];
    $_SESSION['created'][$id] = time();
    $_SESSION['expires'][$id] = time() + $ttl;
    redirect('/created/' . $id);
}

function handle_reveal(string $id): void
{
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        view_error(403, 'Request rejected', 'Your session expired or the form was tampered with. Reload and try again.');
        return;
    }

    $password = $_POST['password'] ?? '';
    if (!is_string($password) || $password === '' || strlen($password) > PASSWORD_MAX) {
        http_response_code(422);
        view_reveal($id, 'Enter the password for this note.');
        return;
    }

    if (!rate_limit('reveal', RATE_REVEAL_MAX)) {
        view_error(429, 'Slow down', 'Too many attempts from this address. Try again shortly.', RATE_WINDOW);
        return;
    }

    $result = reveal_note($id, $password);

    if ($result['status'] === 'ok') {
        view_note($result['body']);
        return;
    }
    if ($result['status'] === 'bad') {
        view_reveal($id, 'That password did not work.');
        return;
    }
    view_error(404, 'Gone', 'This note no longer exists. It expired, was read, or never did.');
}

function created_allowed(string $id): bool
{
    if (!isset($_SESSION['created']) || !is_array($_SESSION['created'])) {
        return false;
    }
    $now = time();
    foreach ($_SESSION['created'] as $key => $timestamp) {
        if (!is_int($timestamp) || $now - $timestamp > 3600) {
            unset($_SESSION['created'][$key]);
        }
    }
    return isset($_SESSION['created'][$id]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'HEAD') {
    $method = 'GET';
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    $path = '/';
}
if ($path !== '/') {
    $path = rtrim($path, '/');
}

if ($method === 'GET' && $path === '/') {
    view_landing();
    exit;
}

if ($method === 'GET' && $path === '/new') {
    redirect('/');
}

if ($method === 'GET' && $path === '/security') {
    view_security();
    exit;
}

if ($method === 'POST' && $path === '/create') {
    handle_create();
    exit;
}

if ($method === 'GET' && $path === '/healthz') {
    security_headers('text/plain; charset=utf-8');
    try {
        db()->query('SELECT 1');
        echo "ok\n";
    } catch (Throwable $error) {
        http_response_code(503);
        echo "unavailable\n";
    }
    exit;
}

if (preg_match('#\A/created/([A-Za-z0-9_-]{16})\z#', $path, $matches) === 1) {
    if ($method !== 'GET') {
        header('Allow: GET');
        view_error(405, 'Method not allowed', 'That address only answers GET requests.');
        exit;
    }
    if (!created_allowed($matches[1])) {
        view_error(404, 'Not found', 'That sealed note page is not available in this session.');
        exit;
    }
    $expires = $_SESSION['expires'][$matches[1]] ?? null;
    view_created($matches[1], is_int($expires) ? $expires : null);
    exit;
}

if (preg_match('#\A/note/([A-Za-z0-9_-]{16})\z#', $path, $matches) === 1) {
    $id = $matches[1];
    if ($method === 'GET') {
        if (random_int(1, 25) === 1) {
            gc_notes();
        }
        if (!note_available($id)) {
            view_error(404, 'Gone', 'This note no longer exists. It expired, was read, or never did.');
            exit;
        }
        view_reveal($id);
        exit;
    }
    if ($method === 'POST') {
        handle_reveal($id);
        exit;
    }
    header('Allow: GET, POST');
    view_error(405, 'Method not allowed', 'That address accepts GET and POST only.');
    exit;
}

if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    view_error(405, 'Method not allowed', 'The server accepts GET and POST only.');
    exit;
}

view_error(404, 'Not found', 'There is nothing at that address.');
