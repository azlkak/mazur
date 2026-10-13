<?php
declare(strict_types=1);

// A single source of truth for the labels displayed on a property page.
function mazurOfferFeatures(array $fields): array
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
