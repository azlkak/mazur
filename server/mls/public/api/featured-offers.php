<?php
declare(strict_types=1);
require_once __DIR__ . '/portal-visibility.php';
require_once __DIR__ . '/offer-source.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
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
    // Deleted or withdrawn offers are omitted immediately without changing the selection.
    [$hiddenIds, $hiddenProperties] = portalHiddenSets($db);
    $rows = $db->query('SELECT f.source, f.source_id, o.fields_json FROM featured_offers f INNER JOIN mls_offers o ON o.source = f.source AND o.source_id = f.source_id AND o.publishable = 1 ORDER BY f.display_order');
    $ids = [];
    foreach ($rows as $row) {
        $fields = json_decode($row['fields_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!portalIsHidden($hiddenIds, $hiddenProperties, (string)$row['source'], (string)$row['source_id'], $fields)) $ids[] = portalPublicOfferId((string)$row['source'], (string)$row['source_id']);
    }
    echo json_encode(['ids' => $ids], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);
    echo '{"error":"Oferty wyróżnione są chwilowo niedostępne"}';
}
