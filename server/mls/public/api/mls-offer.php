<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = ['https://mazurestate.pl', 'https://www.mazurestate.pl', 'https://azlkak.github.io'];
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

function firstText(array $fields, string ...$keys): string
{
    foreach ($keys as $key) {
        $value = trim((string)($fields[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return '';
}

function publicDescription(string $value): string
{
    $value = preg_replace('/<br\s*\/?>/i', "\n", $value) ?? $value;
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace("/\r\n?|\x{00A0}/u", "\n", $value) ?? $value;
    $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value;
    return trim($value);
}

try {
    $id = (string)($_GET['id'] ?? '');
    if (!preg_match('/\A(?:esti-)?[0-9]{1,40}\z/', $id)) {
        http_response_code(400);
        echo '{"error":"Nieprawidłowy numer oferty"}';
        exit;
    }
    $source = str_starts_with($id, 'esti-') ? 'esticrm' : 'mls';
    $sourceId = $source === 'esticrm' ? substr($id, 5) : $id;

    $privateRoot = dirname(__DIR__, 2) . '/mls';
    $config = require $privateRoot . '/config.php';
    $db = new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $statement = $db->prepare(
        'SELECT source, source_id, source_export_at, fields_json, images_json
         FROM mls_offers
         WHERE source = ? AND source_id = ? AND publishable = 1
         LIMIT 1'
    );
    $statement->execute([$source, $sourceId]);
    $row = $statement->fetch();
    if (!$row) {
        http_response_code(404);
        echo '{"error":"Oferta nie jest dostępna"}';
        exit;
    }

    $fields = json_decode($row['fields_json'], true, 512, JSON_THROW_ON_ERROR);
    $images = json_decode($row['images_json'], true, 512, JSON_THROW_ON_ERROR);
    $type = firstText($fields, 'typeName') ?: 'Nieruchomość';
    $city = firstText($fields, 'locationExportCityName', 'locationCityName');
    $district = firstText($fields, 'locationExportPrecinctName', 'locationPrecinctName');
    $province = firstText($fields, 'locationExportProvinceName', 'locationProvinceName');
    $title = firstText($fields, 'portalTitle');
    if ($title === '') $title = $type . ($city !== '' ? ' — ' . $city : '');

    $featureMap = [
        'additionalBalcony' => 'Balkon',
        'additionalTerrace' => 'Taras',
        'additionalGarden' => 'Ogród',
        'additionalParking' => 'Miejsce parkingowe',
        'additionalParkingunderground' => 'Parking podziemny',
        'additionalGarage' => 'Garaż',
        'buildingElevatornumber' => 'Winda',
        'mediaInternet' => 'Internet',
        'securityGated' => 'Teren zamknięty',
    ];
    $features = array_values(array_filter(array_map('trim', explode(',', firstText($fields, 'tagList')))));
    foreach ($featureMap as $field => $label) {
        $value = strtolower(trim((string)($fields[$field] ?? '')));
        if ($value !== '' && !in_array($value, ['0', 'false', 'nie', 'no'], true)) $features[] = $label;
    }
    $features = array_values(array_unique($features));

    $offer = [
        'id' => $source === 'esticrm' ? 'esti-' . $row['source_id'] : (string)$row['source_id'],
        'number' => firstText($fields, 'numberExport', 'number'),
        'title' => $title,
        'type' => $type,
        'mainTypeId' => firstText($fields, 'mainTypeId'),
        'transaction' => match (firstText($fields, 'transaction')) {
            '131' => 'sprzedaż',
            '132' => 'wynajem',
            default => '',
        },
        'city' => $city,
        'district' => $district,
        'province' => $province,
        'price' => (float)($fields['price'] ?? 0),
        'currency' => firstText($fields, 'priceCurrency') === '2' ? 'PLN' : '',
        'area' => (float)($fields['areaTotal'] ?? 0),
        'usableArea' => (float)($fields['areaUsable'] ?? 0),
        'plotArea' => (float)($fields['areaPlot'] ?? 0),
        'rooms' => (int)($fields['apartmentRoomNumber'] ?? 0),
        'floor' => firstText($fields, 'apartmentFloor'),
        'buildingFloors' => firstText($fields, 'buildingFloornumber'),
        'buildingYear' => firstText($fields, 'buildingYear'),
        'description' => publicDescription(firstText($fields, 'descriptionWebsite', 'description')),
        'features' => array_slice($features, 0, 20),
        'exportedAt' => (string)$row['source_export_at'],
        'images' => array_map(
            static fn(string $name): string => 'https://api.mazurestate.pl/api/mls-image.php?name=' . rawurlencode($name),
            array_values(array_filter($images, static fn($name): bool => is_string($name)))
        ),
    ];

    echo json_encode(['demo' => true, 'offer' => $offer], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo '{"error":"Nie udało się pobrać oferty"}';
}
