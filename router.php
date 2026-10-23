<?php
declare(strict_types=1);

$publicRoot = realpath(__DIR__ . '/public');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($publicRoot === false || !is_string($path) || $path === '' || str_contains($path, "\0")) {
    http_response_code(400);
    exit;
}

$candidate = realpath($publicRoot . $path);
if ($candidate !== false
    && is_file($candidate)
    && str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR)
) {
    $extension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
    if (!str_starts_with(basename($candidate), '.')
        && in_array($extension, ['css', 'js', 'txt', 'svg', 'png', 'ico', 'woff2'], true)
    ) {
        return false;
    }
}

require $publicRoot . '/index.php';
