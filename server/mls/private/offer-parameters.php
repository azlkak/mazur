<?php
declare(strict_types=1);

// Keep the feature extraction aligned with public/api/offer-parameters.php.
// The private importer and public API are installed in separate domain trees.
function mazurTrOfferFeatures(array $fields): array
{
    $featureMap = [
        'additionalBalcony' => 'Balkon', 'additionalTerrace' => 'Taras',
        'additionalGarden' => 'Ogród', 'additionalAttic' => 'Poddasze',
        'additionalBasement' => 'Piwnica', 'additionalStorage' => 'Komórka lokatorska',
        'additionalEntresol' => 'Antresola', 'additionalLoggia' => 'Loggia',
        'additionalParking' => 'Miejsce parkingowe',
        'additionalParkingunderground' => 'Parking podziemny',
        'additionalGarage' => 'Garaż', 'buildingElevatornumber' => 'Winda',
        'buildingSwimmingpool' => 'Basen', 'buildingAirConditioning' => 'Klimatyzacja',
        'buildingAdapted' => 'Dostępne dla osób z niepełnosprawnościami',
        'securityIntercom' => 'Domofon', 'securityGuarded' => 'Osiedle strzeżone',
        'securityReception' => 'Recepcja', 'securityVideointercom' => 'Wideodomofon',
        'securityGated' => 'Teren zamknięty', 'securitySecuredoor' => 'Drzwi antywłamaniowe',
        'securityBlinds' => 'Rolety', 'securityVideocameras' => 'Monitoring',
        'securityMonitoring' => 'Monitoring', 'securityAlarm' => 'Alarm',
        'mediaInternet' => 'Internet', 'mediaTelevision' => 'Telewizja kablowa',
    ];
    $features = array_values(array_filter(array_map('trim', explode(',', (string)($fields['tagList'] ?? '')))));
    foreach ($featureMap as $field => $label) {
        $value = strtolower(trim((string)($fields[$field] ?? '')));
        // EstiCRM uses both 0/1 and the binary dictionary 27/28.
        if ($value !== '' && !in_array($value, ['0', '27', 'false', 'nie', 'no'], true)
            && (!is_numeric($value) || (float)$value > 0)) $features[] = $label;
    }
    // These enum IDs are verified against the EstiCRM export and the offer's
    // Parameters screen. Unknown codes are intentionally never shown raw.
    if (in_array(trim((string)($fields['apartmentFurnishings'] ?? '')), ['150', '1'], true)) $features[] = 'Umeblowane';
    if (trim((string)($fields['brightKitchen'] ?? '')) === '154') $features[] = 'Jasna kuchnia';
    if (trim((string)($fields['apartmentKitchen'] ?? '')) === '422') $features[] = 'Aneks kuchenny';
    if (trim((string)($fields['apartmentBathroomType'] ?? '')) === '563') $features[] = 'Wanna i prysznic';
    if (trim((string)($fields['buildingCondition'] ?? '')) === '293') $features[] = 'Wysoki standard';
    if (!in_array(strtolower(trim((string)($fields['apartmentEquipment'] ?? ''))), ['', '0', '27', 'false', 'nie', 'no'], true)) $features[] = 'Wyposażenie';
    foreach (['apartmentBedroomNumber' => ['sypialnia', 'sypialnie', 'sypialni'],
              'apartmentBathroomNumber' => ['łazienka', 'łazienki', 'łazienek'],
              'apartmentToiletNumber' => ['toaleta', 'toalety', 'toalet']] as $field => $forms) {
        $value = trim((string)($fields[$field] ?? ''));
        if (!ctype_digit($value) || (int)$value < 1 || (int)$value > 30) continue;
        $count = (int)$value;
        $form = $count === 1 ? $forms[0] : (($count % 10 >= 2 && $count % 10 <= 4 && ($count % 100 < 12 || $count % 100 > 14)) ? $forms[1] : $forms[2]);
        $features[] = $count . ' ' . $form;
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
