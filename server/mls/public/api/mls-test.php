<?php
declare(strict_types=1);
require_once __DIR__ . '/portal-visibility.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = ['https://mazurestate.pl', 'https://www.mazurestate.pl', 'https://azlkak.github.io'];
if (in_array($origin, $allowedOrigins, true)) {
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
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);
    [$hiddenIds, $hiddenProperties] = portalHiddenSets($db);
    $hasSource = portalOffersHaveSource($db);
    $rows = $db->query($hasSource
        ? "SELECT source, source_id, source_export_at, fields_json, images_json FROM mls_offers WHERE publishable = 1 ORDER BY (source = 'esticrm') DESC, source_export_at DESC, source_id DESC"
        : "SELECT 'mls' AS source, source_id, source_export_at, fields_json, images_json FROM mls_offers WHERE publishable = 1 ORDER BY source_export_at DESC, source_id DESC"
    );

    $offers = [];
    $seenProperties = [];
    foreach ($rows as $row) {
        $fields = json_decode($row['fields_json'], true, 512, JSON_THROW_ON_ERROR);
        if (portalIsHidden($hiddenIds, $hiddenProperties, (string)$row['source'], (string)$row['source_id'], $fields)) continue;
        $images = json_decode($row['images_json'], true, 512, JSON_THROW_ON_ERROR);
        $companyId = trim((string)($fields['companyId'] ?? ''));
        $number = trim((string)($fields['number'] ?? ''));
        $propertyKey = $companyId !== '' && $number !== '' ? $companyId . ':' . $number : '';
        if ($propertyKey !== '' && isset($seenProperties[$propertyKey])) continue;
        if ($propertyKey !== '') $seenProperties[$propertyKey] = true;
        $city = trim((string)(($fields['locationExportCityName'] ?? '') ?: ($fields['locationCityName'] ?? '')));
        $district = trim((string)(($fields['locationExportPrecinctName'] ?? '') ?: ($fields['locationPrecinctName'] ?? '')));
        $type = trim((string)($fields['typeName'] ?? 'Nieruchomość'));
        $title = trim((string)($fields['portalTitle'] ?? ''));
        if ($title === '') $title = $type . ($city !== '' ? ' — ' . $city : '');

        $offers[] = [
            'id' => $row['source'] === 'esticrm' ? 'esti-' . $row['source_id'] : (string)$row['source_id'],
            'number' => (string)($fields['numberExport'] ?? $fields['number'] ?? ''),
            'title' => $title,
            'type' => $type,
            'transaction' => match ((string)($fields['transaction'] ?? '')) {
                '131' => 'sprzedaż',
                '132' => 'wynajem',
                default => '',
            },
            'city' => $city,
            'district' => $district,
            'price' => (float)($fields['price'] ?? 0),
            'currency' => ((string)($fields['priceCurrency'] ?? '')) === '2' ? 'PLN' : '',
            'area' => (float)($fields['areaTotal'] ?? 0),
            'plotArea' => (float)($fields['areaPlot'] ?? 0),
            'rooms' => (int)($fields['apartmentRoomNumber'] ?? 0),
            'floor' => (string)($fields['apartmentFloor'] ?? ''),
            'exportedAt' => (string)$row['source_export_at'],
            'images' => array_map(
                static fn(string $name): string => 'https://api.mazurestate.pl/api/mls-image.php?name=' . rawurlencode($name),
                array_slice(array_values(array_filter($images, static fn($name): bool => is_string($name))), 0, 5)
            ),
        ];
    }

    echo json_encode([
        'demo' => false,
        'count' => count($offers),
        'offers' => $offers,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo '{"error":"Nie udało się pobrać ofert"}';
}
