<?php
declare(strict_types=1);
require_once __DIR__ . '/portal-visibility.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (in_array($origin, ['https://mazurestate.pl', 'https://www.mazurestate.pl', 'https://azlkak.github.io'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

try {
    $privateRoot = dirname(__DIR__, 2) . '/mls';
    $config = require $privateRoot . '/config.php';
    $db = new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    [$hiddenIds, $hiddenProperties] = portalHiddenSets($db);
    $sourceColumn = portalOffersHaveSource($db) ? 'source' : "'mls' AS source";
    $rows = [];
    foreach ($db->query("SELECT $sourceColumn, source_id, location_city AS city, location_district AS district, fields_json FROM mls_offers WHERE publishable = 1 AND location_city <> '' ORDER BY location_city, location_district") as $row) {
        $fields = json_decode($row['fields_json'], true, 512, JSON_THROW_ON_ERROR);
        if (portalIsHidden($hiddenIds, $hiddenProperties, (string)$row['source'], (string)$row['source_id'], $fields)) continue;
        $key = $row['city'] . "\0" . $row['district'];
        $rows[$key] = ['city' => $row['city'], 'district' => $row['district']];
    }
    $rows = array_values($rows);

    echo json_encode([
        'count' => count($rows),
        'locations' => $rows,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    header('Cache-Control: no-store');
    echo '{"error":"Nie udało się pobrać lokalizacji"}';
}
