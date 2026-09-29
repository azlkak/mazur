<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Content-Type-Options: nosniff');
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (in_array($origin, ['https://mazurestate.pl', 'https://www.mazurestate.pl', 'https://azlkak.github.io'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

try {
    $config = require dirname(__DIR__, 2) . '/mls/config.php';
    $db = new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Deleted or withdrawn MLS offers are omitted immediately without changing the selection.
    $rows = $db->query('SELECT f.source_id FROM featured_offers f INNER JOIN mls_offers o ON o.source_id = f.source_id AND o.publishable = 1 ORDER BY f.display_order');
    echo json_encode(['ids' => $rows->fetchAll(PDO::FETCH_COLUMN)], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);
    echo '{"error":"Oferty wyróżnione są chwilowo niedostępne"}';
}
