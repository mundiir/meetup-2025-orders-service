<?php
// Router for PHP built-in server to forward non-existing paths to index.php
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if (is_file($file)) {
        return false; // serve the requested resource as-is
    }
}
require __DIR__ . '/index.php';

