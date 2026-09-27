<?php
declare(strict_types=1);

$name = (string)($_GET['name'] ?? '');
if (!preg_match('/\A[a-f0-9]{64}\.(?:jpg|png)\z/', $name)) {
    http_response_code(404);
    exit;
}

$imageRoot = dirname(__DIR__, 2) . '/mls/images';
$path = $imageRoot . '/' . $name;
if (!is_file($path) || is_link($path)) {
    http_response_code(404);
    exit;
}

$type = strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';
header('Content-Type: ' . $type);
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: public, max-age=604800, immutable');
header('X-Content-Type-Options: nosniff');
readfile($path);
