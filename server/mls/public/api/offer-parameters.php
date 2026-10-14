<?php
declare(strict_types=1);

// A single source of truth for the labels displayed on a property page.
function mazurOfferFeatures(array $fields): array
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

function mazurOfferParameterPhrases(array $fields): array
{
    $phrases = [];
    foreach (mazurOfferFeatures($fields) as $feature) $phrases['feature:' . $feature] = ['feature', $feature];
    $type = trim((string)($fields['typeName'] ?? ''));
    if ($type !== '') $phrases['type:' . $type] = ['type', $type];
    $floor = trim((string)($fields['apartmentFloor'] ?? ''));
    if ($floor !== '' && preg_match('/[\p{L}]/u', $floor)) $phrases['floor:' . $floor] = ['floor', $floor];
    $province = trim((string)($fields['locationExportProvinceName'] ?? ''));
    if ($province === '') $province = trim((string)($fields['locationProvinceName'] ?? ''));
    if ($province !== '') $phrases['province:' . $province] = ['province', $province];
    return array_values($phrases);
}

function mazurOfferParameterHash(string $kind, string $text): string
{
    return hash('sha256', $kind . "\0" . $text);
}

function mazurOfferParameterTranslations(PDO $db, array $fields, string $language): array
{
    if (!in_array($language, ['en', 'uk', 'ru'], true)) return [];
    $phrases = mazurOfferParameterPhrases($fields);
    if (!$phrases) return [];
    $byHash = [];
    foreach ($phrases as [$kind, $text]) $byHash[mazurOfferParameterHash($kind, $text)] = [$kind, $text];
    try {
        $marks = implode(',', array_fill(0, count($byHash), '?'));
        $query = $db->prepare("SELECT phrase_hash,translated_text FROM offer_parameter_translations WHERE language=? AND state='done' AND phrase_hash IN ($marks)");
        $query->execute(array_merge([$language], array_keys($byHash)));
        $out = ['features' => []];
        foreach ($query as $row) {
            [$kind, $source] = $byHash[$row['phrase_hash']];
            $translated = trim((string)$row['translated_text']);
            if ($translated === '') continue;
            if ($kind === 'feature') $out['features'][$source] = $translated;
            else $out[$kind] = $translated;
        }
        $query->closeCursor();
        return $out;
    } catch (Throwable $error) {
        // During a rolling deployment, the new table may not exist yet.
        error_log('Mazur offer parameter translation read unavailable: ' . get_class($error));
        return [];
    }
}
