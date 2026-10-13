<?php
declare(strict_types=1);

// Keep the feature extraction aligned with public/api/offer-parameters.php.
// The private importer and public API are installed in separate domain trees.
function mazurTrOfferFeatures(array $fields): array
{
    $featureMap = [
        'additionalBalcony' => 'Balkon', 'additionalTerrace' => 'Taras',
        'additionalGarden' => 'Ogród', 'additionalParking' => 'Miejsce parkingowe',
        'additionalParkingunderground' => 'Parking podziemny',
        'additionalGarage' => 'Garaż', 'buildingElevatornumber' => 'Winda',
        'mediaInternet' => 'Internet', 'securityGated' => 'Teren zamknięty',
    ];
    $features = array_values(array_filter(array_map('trim', explode(',', (string)($fields['tagList'] ?? '')))));
    foreach ($featureMap as $field => $label) {
        $value = strtolower(trim((string)($fields[$field] ?? '')));
        if ($value !== '' && !in_array($value, ['0', 'false', 'nie', 'no'], true)) $features[] = $label;
    }
    return array_slice(array_values(array_unique($features)), 0, 20);
}

function mazurTrParameterPhrases(array $fields): array
{
    $phrases = [];
    foreach (mazurTrOfferFeatures($fields) as $feature) $phrases['feature:' . $feature] = ['feature', $feature];
    $type = trim((string)($fields['typeName'] ?? ''));
    if ($type !== '') $phrases['type:' . $type] = ['type', $type];
    $floor = trim((string)($fields['apartmentFloor'] ?? ''));
    if ($floor !== '' && preg_match('/[\p{L}]/u', $floor)) $phrases['floor:' . $floor] = ['floor', $floor];
    $province = trim((string)($fields['locationExportProvinceName'] ?? ''));
    if ($province === '') $province = trim((string)($fields['locationProvinceName'] ?? ''));
    if ($province !== '') $phrases['province:' . $province] = ['province', $province];
    return array_values($phrases);
}

function mazurTrParameterHash(string $kind, string $text): string
{
    return hash('sha256', $kind . "\0" . $text);
}
