<?php
declare(strict_types=1);

// Shared read model for the server-rendered offer page and the offer sitemap.
// This file has no public output when requested directly.
require_once __DIR__ . '/portal-visibility.php';
require_once __DIR__ . '/offer-source.php';
if (is_file(__DIR__ . '/description-formatter.php')) {
    require_once __DIR__ . '/description-formatter.php';
}

function mazurSeoDb(): PDO
{
    // The public site is staged under public_html/site while the importer
    // remains in the sibling private domains/mazurestate.pl/mls directory.
    $configPath = dirname(__DIR__, 2) . '/mls/config.php';
    if (!is_file($configPath)) $configPath = dirname(__DIR__, 3) . '/mls/config.php';
    $config = require $configPath;
    $db = new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);
    $db->exec("SET time_zone = '+00:00'");
    return $db;
}

function mazurSeoText(array $fields, string ...$keys): string
{
    foreach ($keys as $key) {
        $value = trim((string)($fields[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return '';
}

function mazurSeoPlainDescription(string $value): string
{
    $value = preg_replace('~<(?:br|hr)\b[^>]*>|</(?:p|div|h[1-6]|li|ul|ol|section|article|blockquote|tr|td|th)\s*>~i', "\n", $value) ?? $value;
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace(["\r\n", "\r", "\u{00A0}", "\u{202F}"], ["\n", "\n", ' ', ' '], $value);
    $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value;
    return trim($value);
}

function mazurSeoFindOffer(PDO $db, string $id): ?array
{
    $parsedId = portalParseOfferId($id);
    if ($parsedId === null) return null;
    [$source, $sourceId] = $parsedId;
    $hasSource = portalOffersHaveSource($db);
    if ($source !== 'mls' && !$hasSource) return null;
    $statement = $db->prepare($hasSource
        ? 'SELECT source, source_id, source_export_at, fields_json, images_json FROM mls_offers WHERE source=? AND source_id=? AND publishable=1 LIMIT 1'
        : "SELECT 'mls' AS source, source_id, source_export_at, fields_json, images_json FROM mls_offers WHERE source_id=? AND publishable=1 LIMIT 1"
    );
    $statement->execute($hasSource ? [$source, $sourceId] : [$sourceId]);
    $row = $statement->fetch();
    $statement->closeCursor();
    if (!$row) return null;
    $fields = json_decode($row['fields_json'], true, 512, JSON_THROW_ON_ERROR);
    [$hiddenIds, $hiddenProperties] = portalHiddenSets($db);
    if (portalIsHidden($hiddenIds, $hiddenProperties, $source, $sourceId, $fields)) return null;
    return mazurSeoPublicOffer($row, $fields);
}

function mazurSeoPublicOffer(array $row, array $fields): array
{
    $images = json_decode($row['images_json'], true, 512, JSON_THROW_ON_ERROR);
    $type = mazurSeoText($fields, 'typeName') ?: 'Nieruchomość';
    $city = mazurSeoText($fields, 'locationExportCityName', 'locationCityName');
    $title = mazurSeoText($fields, 'portalTitle') ?: $type . ($city !== '' ? ' — ' . $city : '');
    $rawDescription = mazurSeoText($fields, 'descriptionWebsite', 'description');
    $featureMap = [
        'additionalBalcony' => 'Balkon', 'additionalTerrace' => 'Taras',
        'additionalGarden' => 'Ogród', 'additionalParking' => 'Miejsce parkingowe',
        'additionalParkingunderground' => 'Parking podziemny',
        'additionalGarage' => 'Garaż', 'buildingElevatornumber' => 'Winda',
        'mediaInternet' => 'Internet', 'securityGated' => 'Teren zamknięty',
    ];
    $features = array_values(array_filter(array_map('trim', explode(',', mazurSeoText($fields, 'tagList')))));
    foreach ($featureMap as $field => $label) {
        $value = strtolower(trim((string)($fields[$field] ?? '')));
        if ($value !== '' && !in_array($value, ['0', 'false', 'nie', 'no'], true)) $features[] = $label;
    }
    $images = array_values(array_filter($images, static fn($name): bool => is_string($name)));
    return [
        'id' => portalPublicOfferId((string)$row['source'], (string)$row['source_id']),
        'number' => mazurSeoText($fields, 'numberExport', 'number'),
        'title' => $title,
        'type' => $type,
        'mainTypeId' => mazurSeoText($fields, 'mainTypeId'),
        'transaction' => match (mazurSeoText($fields, 'transaction')) {
            '131' => 'sprzedaż', '132' => 'wynajem', default => '',
        },
        'city' => $city,
        'district' => mazurSeoText($fields, 'locationExportPrecinctName', 'locationPrecinctName'),
        'province' => mazurSeoText($fields, 'locationExportProvinceName', 'locationProvinceName'),
        'price' => (float)($fields['price'] ?? 0),
        'currency' => mazurSeoText($fields, 'priceCurrency') === '2' ? 'PLN' : '',
        'area' => (float)($fields['areaTotal'] ?? 0),
        'usableArea' => (float)($fields['areaUsable'] ?? 0),
        'plotArea' => (float)($fields['areaPlot'] ?? 0),
        'rooms' => (int)($fields['apartmentRoomNumber'] ?? 0),
        'floor' => mazurSeoText($fields, 'apartmentFloor'),
        'buildingFloors' => mazurSeoText($fields, 'buildingFloornumber'),
        'buildingYear' => mazurSeoText($fields, 'buildingYear'),
        'description' => mazurSeoPlainDescription($rawDescription),
        'descriptionDocument' => function_exists('mazurDescriptionDocument')
            ? mazurDescriptionDocument($rawDescription) : null,
        'descriptionLanguage' => 'pl',
        'features' => array_slice(array_values(array_unique($features)), 0, 20),
        'exportedAt' => (string)$row['source_export_at'],
        'images' => array_map(
            static fn(string $name): string => portalImageUrl($name),
            $images
        ),
    ];
}

function mazurSeoVisibleOfferUrls(PDO $db): Generator
{
    [$hiddenIds, $hiddenProperties] = portalHiddenSets($db);
    $hasSource = portalOffersHaveSource($db);
    $stateStatement = $db->query("SHOW TABLES LIKE 'offer_seo_state'");
    $hasState = $stateStatement->fetch() !== false;
    $stateStatement->closeCursor();
    $stateJoin = $hasState
        ? ' LEFT JOIN offer_seo_state s ON s.source=o.source AND s.source_id=o.source_id'
        : '';
    $stateField = $hasState ? ', s.modified_at AS seo_modified_at' : '';
    $query = $hasSource
        ? "SELECT o.source, o.source_id, o.fields_json{$stateField} FROM mls_offers o{$stateJoin} WHERE o.publishable=1 ORDER BY (o.source='esticrm') DESC, o.source_export_at DESC, o.source_id DESC"
        : "SELECT 'mls' AS source, o.source_id, o.fields_json FROM mls_offers o WHERE o.publishable=1 ORDER BY o.source_export_at DESC, o.source_id DESC";
    $seenProperties = [];
    foreach ($db->query($query) as $row) {
        $fields = json_decode($row['fields_json'], true, 512, JSON_THROW_ON_ERROR);
        $source = (string)$row['source'];
        $sourceId = (string)$row['source_id'];
        if (portalIsHidden($hiddenIds, $hiddenProperties, $source, $sourceId, $fields)) continue;
        $propertyKey = portalPropertyKey($fields);
        if ($propertyKey !== '' && isset($seenProperties[$propertyKey])) continue;
        if ($propertyKey !== '') $seenProperties[$propertyKey] = true;
        yield [
            'id' => portalPublicOfferId($source, $sourceId),
            'lastmod' => (string)($row['seo_modified_at'] ?? ''),
        ];
    }
}
