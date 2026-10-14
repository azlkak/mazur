<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/mls/public/api/description-formatter.php';
require_once __DIR__ . '/../server/mls/private/offer-translations.php';
require_once __DIR__ . '/../server/mls/private/bin/translate-offers.php';
require_once __DIR__ . '/../server/mls/public/api/offer-parameters.php';

function ensure(bool $okay, string $name): void
{
    if (!$okay) throw new RuntimeException('FAIL: ' . $name);
    echo 'PASS: ' . $name . PHP_EOL;
}

$parameterFields = [
    'tagList' => 'Dla studenta, Dla rodziny z dziećmi, Dla studenta',
    'additionalBalcony' => '1', 'typeName' => 'Mieszkanie',
    'apartmentFloor' => 'Parter', 'locationExportProvinceName' => 'MAZOWIECKIE',
];
ensure(mazurOfferFeatures($parameterFields) === ['Dla studenta', 'Dla rodziny z dziećmi', 'Balkon'], 'feature list is deduplicated before translation');
$estiFields = [
    'additionalGarden' => '1', 'additionalAttic' => '1',
    'apartmentFurnishings' => '150', 'brightKitchen' => '154',
    'apartmentKitchen' => '422', 'apartmentBathroomType' => '563',
    'buildingCondition' => '293', 'apartmentEquipment' => '584,585,586',
    'apartmentBedroomNumber' => '3', 'apartmentBathroomNumber' => '2',
    'apartmentToiletNumber' => '2', 'securityGated' => '27',
];
ensure(mazurOfferFeatures($estiFields) === [
    'Ogród', 'Poddasze', 'Umeblowane', 'Jasna kuchnia', 'Aneks kuchenny',
    'Wanna i prysznic', 'Wysoki standard', 'Wyposażenie',
    '3 sypialnie', '2 łazienki', '2 toalety',
], 'EstiCRM parameters become readable features without false flags or raw enum IDs');
ensure(mazurTrOfferFeatures($estiFields) === mazurOfferFeatures($estiFields), 'private translator sees the same EstiCRM features as the public API');
$phrases = mazurOfferParameterPhrases($parameterFields);
ensure(count($phrases) === 6 && in_array(['floor', 'Parter'], $phrases, true), 'visible parameter phrases include free-text features and floor');
ensure(mazurOfferParameterHash('feature', 'Balkon') === mazurOfferParameterHash('feature', 'Balkon') && mazurOfferParameterHash('feature', 'Balkon') !== mazurOfferParameterHash('type', 'Balkon'), 'shared phrase cache is stable and scoped to kind');
ensure(mazurTrParameterPhrases($parameterFields) === $phrases && mazurTrParameterHash('feature', 'Balkon') === mazurOfferParameterHash('feature', 'Balkon'), 'private worker and public API agree on phrases and cache keys');

$fields = ['portalTitle' => 'Mieszkanie na Mokotowie', 'descriptionWebsite' => "Salon z balkonem.\nBlisko metra.\nMożliwość parkowania."];
[$title, $document] = mazurTrSource($fields);
[$first, $skeleton] = mazurTrCandidateDocument($title, $document);
$first = mazurTrAssignSegments($first, []);
$skeleton = mazurTrFillSkeleton($skeleton, $first);
ensure(count($first) === 4 && $first[0]['segment_id'] === 'title', 'title and three independent description fragments');

$sameFields = $fields;
$sameFields['price'] = '1200000';
[$title, $document] = mazurTrSource($sameFields);
[$same, $sameSkeleton] = mazurTrCandidateDocument($title, $document);
$same = mazurTrAssignSegments($same, $first);
$sameSkeleton = mazurTrFillSkeleton($sameSkeleton, $same);
ensure(mazurTrSignature($same, $sameSkeleton) === mazurTrSignature($first, $skeleton), 'price-only change does not invalidate text');

$changedFields = $fields;
$changedFields['descriptionWebsite'] = "Salon z balkonem.\nBardzo blisko metra.\nMożliwość parkowania.";
[$title, $document] = mazurTrSource($changedFields);
[$changed, $changedSkeleton] = mazurTrCandidateDocument($title, $document);
$changed = mazurTrAssignSegments($changed, $first);
ensure($changed[1]['pl_hash'] === $first[1]['pl_hash'] && $changed[3]['pl_hash'] === $first[3]['pl_hash'], 'unchanged fragments retain hashes');
ensure($changed[2]['segment_id'] === $first[2]['segment_id'] && $changed[2]['pl_hash'] !== $first[2]['pl_hash'], 'edited fragment keeps identity but needs translation');

$movedFields = $fields;
$movedFields['descriptionWebsite'] = "Możliwość parkowania.\nSalon z balkonem.\nBlisko metra.";
[$title, $document] = mazurTrSource($movedFields);
[$moved] = mazurTrCandidateDocument($title, $document);
$moved = mazurTrAssignSegments($moved, $first);
ensure($moved[1]['segment_id'] === $first[3]['segment_id'] && $moved[2]['segment_id'] === $first[1]['segment_id'], 'reordered fragments reuse translations');

$translated = [];
foreach ($first as $segment) $translated[$segment['segment_id']] = [['text' => 'EN: ' . $segment['pl_text']]];
$assembled = mazurTrFillSkeleton($skeleton, $first, $translated);
ensure($assembled['blocks'][0]['runs'][0]['text'] === 'EN: Salon z balkonem.', 'translated units assemble into the original structure');

[$protected, $tokens] = trProtectRuns([['text' => 'Cena 1 200 000 zł, kontakt info@mazurestate.pl.']]);
ensure(count($tokens) === 2 && str_contains($protected[0]['text'], '[[ME0]]'), 'amount and email protected before API call');
$translatedRuns = trValidateRuns([['text' => 'Price [[ME0]] PLN, contact [[ME1]].', 'strong' => false, 'em' => false]], $tokens, 55);
ensure(str_contains($translatedRuns[0]['text'], '1 200 000') && str_contains($translatedRuns[0]['text'], 'info@mazurestate.pl'), 'protected values restored after translation');
try {
    trValidateRuns([['text' => 'Price without original number', 'strong' => false, 'em' => false]], $tokens, 55);
    throw new RuntimeException('Missing token was accepted');
} catch (RuntimeException $error) {
    ensure(str_contains($error->getMessage(), 'Protected value'), 'missing protected value rejects translation');
}
try {
    trValidateRuns([['text' => 'Price [[ME0]] and extra 3, contact [[ME1]].', 'strong' => false, 'em' => false]], $tokens, 55);
    throw new RuntimeException('Invented number was accepted');
} catch (RuntimeException $error) {
    ensure(str_contains($error->getMessage(), 'Unexpected number'), 'invented number rejects translation');
}

function translated(string $text, bool $strong = false, bool $em = false): array
{
    return [['text' => $text, 'strong' => $strong, 'em' => $em]];
}

function validateTranslation(string $source, string $target): array
{
    $sourceRuns = [['text' => $source]];
    [, $tokens] = trProtectRuns($sourceRuns);
    return trValidateRuns(translated($target), $tokens, strlen($source), $sourceRuns);
}

function rejectsTranslation(string $source, string $target, string $message): void
{
    $rejected = false;
    try { validateTranslation($source, $target); }
    catch (RuntimeException $error) { $rejected = str_contains($error->getMessage(), $message); }
    ensure($rejected, $source . ' rejects ' . $target);
}

foreach ([
    ['Dwupokojowe mieszkanie', '2-room apartment'],
    ['Dwa pokoje z balkonem', '2 rooms with a balcony'],
    ['Dwupokojowe mieszkanie', '2-кімнатна квартира'],
    ['Dwupokojowe mieszkanie', '2-комнатная квартира'],
    ['Na trzecim piętrze', 'On the 3rd floor'],
    ['Na trzecim piętrze', 'На 3-му поверсі'],
    ['Na trzecim piętrze', 'На 3-м этаже'],
    ['III piętro | winda,', '3rd floor | elevator,'],
    ['III piętro | winda,', '3-й этаж | лифт,'],
    ['Piętro III', 'Floor 3'],
    ['Na jedenastym piętrze', '11th floor'],
    ['Obsługa ochrony przez całą dobę', '24-hour security'],
    ['Całodobowo monitorowany', 'Monitored 24 hours a day'],
    ['Całodobowa ochrona', '24h security'],
    ['Całodobowa ochrona', '24-годинна охорона'],
    ['Całodobowa ochrona', '24-часовая охрана'],
    ['Całodobowa ochrona', 'Охорона 24 години на добу'],
    ['Całodobowa ochrona', 'Охрана 24 часа в сутки'],
] as [$source, $target]) {
    ensure(validateTranslation($source, $target)[0]['text'] === $target, 'semantically equivalent numeric form: ' . $target);
}

foreach ([
    ['-całodobowy monitoring i portiernia,', '- 24h monitoring and concierge service,'],
    ['- Całodobowy dostęp do biura', '- 24h office access'],
    ['- całodobowa ochrona,', '- 24h security,'],
    ['+całodobowa portiernia', '+ 24h concierge service'],
    ['- Całodobowa ochrona i portiernia, domofony', '- 24h security and concierge service, intercoms'],
    ['Bezpieczeństwo i komfort - całodobowy monitoring, ochrona, wideodomofon oraz drzwi antywłamaniowe.', 'Security and comfort - 24h monitoring, security, video intercom and security doors.'],
    ['- całodobowa ochrona i monitoring,', '- 24h security and monitoring,'],
    ['• Całodobowa ochrona', '• 24h security'],
    ['— Całodobowa ochrona', '— 24h security'],
    ['- Całodobowa ochrona', '- 24-hour security'],
    ['Całodobowa ochrona', 'Охорона 24h'],
    ['Całodobowa ochrona', 'Охрана 24h'],
    ["Udogodnienia:\n- całodobowa ochrona", "Features:\n- 24h security"],
] as [$source, $target]) {
    ensure(validateTranslation($source, $target)[0]['text'] === $target, 'round-the-clock list marker: ' . $target);
}
foreach (['-24h security', '- 24h security', '+ 24h security', '€ 24h security', '$24h security', '24h security, 24h reception', '23h security', '25h security'] as $target) {
    rejectsTranslation('Całodobowa ochrona', $target, 'Unexpected number');
}
rejectsTranslation('- Całodobowa ochrona', '- 24h security, 7 days a week', 'Unexpected number');
rejectsTranslation('- Całodobowa ochrona', '- 24h security, price 24 PLN', 'Unexpected number');
rejectsTranslation('- Całodobowa ochrona', '€ - 24h security', 'Unexpected number');
rejectsTranslation('- Całodobowa ochrona', '+ 24h security', 'Unexpected number');
rejectsTranslation('- Całodobowa ochrona', '- 24h security, - 24h reception', 'Unexpected number');

require_once __DIR__ . '/../server/mls/private/bin/repair-20261014-translations.php';
foreach (repair24Translations() as [, , $polish, $english]) {
    ensure(validateTranslation($polish, $english)[0]['text'] === $english, 'approved manual 24h repair passes validation');
}
ensure(vixCorrectDescription('O-5', 'Domy otoczon3 ogrodem') === 'Domy otoczone ogrodem', 'approved O-5 source typo corrected');
ensure(vixCorrectDescription('O-50', 'Domy otoczon3 ogrodem') === 'Domy otoczon3 ogrodem', 'other listings untouched by O-5 correction');
ensure(vixCorrectDescription('O-5', 'xotoczon3 otoczon30 otoczone') === 'xotoczon3 otoczon30 otoczone', 'only exact typo is replaced');

rejectsTranslation('Dwupokojowe mieszkanie', '3-room apartment', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '2-bedroom apartment', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '2 спальни', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', 'Price 2 PLN', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '2 rooms, 2 bedrooms', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '2.5 rooms', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '-2 rooms', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '+2 rooms', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '- 2 rooms', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '+ 2 rooms', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '€2 rooms', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '€ 2 rooms', 'Unexpected number');
rejectsTranslation('Dwupokojowe mieszkanie', '２ rooms', 'Unexpected number');
rejectsTranslation('Na trzecim piętrze', '2nd floor', 'Unexpected number');
rejectsTranslation('Na trzecim piętrze', '3 rooms', 'Unexpected number');
rejectsTranslation('III piętro', '3 floors', 'Unexpected number');
rejectsTranslation('III piętro', '3 storeys', 'Unexpected number');
rejectsTranslation('III piętro', '3-storey building', 'Unexpected number');
rejectsTranslation('III piętro', '3-story house', 'Unexpected number');
rejectsTranslation('III piętro', '3-floor building', 'Unexpected number');
rejectsTranslation('III piętro', '3 поверхів', 'Unexpected number');
rejectsTranslation('III piętro', '3 этажей', 'Unexpected number');
rejectsTranslation('III piętro', '3 этажа', 'Unexpected number');
rejectsTranslation('Na dwudziestym trzecim piętrze', 'On the 3rd floor', 'Unexpected number');
rejectsTranslation('Na sto dwudziestym piętrze', 'On the 20th floor', 'Unexpected number');
rejectsTranslation('Piętro dwudzieste trzecie', 'Floor 20', 'Unexpected number');
rejectsTranslation('Dwadzieścia dwa pokoje', '2 rooms', 'Unexpected number');
foreach (['Pięćset', 'Sześćset', 'Siedemset', 'Osiemset', 'Dziewięćset'] as $hundred) {
    rejectsTranslation($hundred . ' dwa pokoje', '2 rooms', 'Unexpected number');
    rejectsTranslation('Na ' . $hundred . ' trzecim piętrze', '3rd floor', 'Unexpected number');
}
rejectsTranslation('III piętro', '3rd floor, phone 3', 'Unexpected number');
rejectsTranslation('Całodobowa ochrona', '24/7 security', 'Unexpected number');
rejectsTranslation('Całodobowa ochrona', '24-hour security, 7 days a week', 'Unexpected number');
rejectsTranslation('Całodobowa ochrona', 'Price 24 PLN', 'Unexpected number');
rejectsTranslation('Całodobowa ochrona', '$24-hour security', 'Unexpected number');
rejectsTranslation('Całodobowa ochrona', '24 годинників', 'Unexpected number');
rejectsTranslation('Całodobowa ochrona', '24 частини', 'Unexpected number');
rejectsTranslation('Całodobowa ochrona', '24 части', 'Unexpected number');
rejectsTranslation('Sto dwadzieścia cztery godziny', '24 hours', 'Unexpected number');
rejectsTranslation('Całodobowa ochrona', '24-hour security, 24-hour reception', 'Unexpected number');
rejectsTranslation('Ochrona i monitoring', '24-hour security', 'Unexpected number');
rejectsTranslation('Salon z balkonem', '2 rooms with balcony', 'Unexpected number');
rejectsTranslation('ENG', 'English text from floor 3', 'Unexpected number');

$currentSource = [['text' => 'Cena 1 100 000 PLN, telefon +48 503 937 749, powierzchnia 55 m².']];
[, $currentTokens] = trProtectRuns($currentSource);
$currentTranslation = trValidateRuns(translated('Price [[ME0]] PLN, phone [[ME1]], area [[ME2]] m².'), $currentTokens, strlen($currentSource[0]['text']), $currentSource);
ensure(str_contains($currentTranslation[0]['text'], '1 100 000') && str_contains($currentTranslation[0]['text'], '+48 503 937 749') && str_contains($currentTranslation[0]['text'], '55 m²'), 'current CRM price, phone and area restored exactly');
rejectsTranslation($currentSource[0]['text'], 'Old price 1 200 000 PLN, current [[ME0]], phone [[ME1]], area [[ME2]].', 'Unexpected number');
rejectsTranslation('Trzy pokoje, cena 900 000 PLN', '3 rooms, price [[ME0]] PLN, previous price 950000 PLN', 'Unexpected number');
ensure(validateTranslation('Metraż 55 m². III piętro.', 'Area [[ME0]] m². 3rd floor.')[0]['text'] === 'Area 55 m². 3rd floor.', 'protected digits and derived floor coexist');
ensure(validateTranslation('Odległości: 250m, 800m i 1km.', 'Distances: [[ME0]]m, [[ME1]]m and [[ME2]]km.')[0]['text'] === 'Distances: 250m, 800m and 1km.', 'all current distance values survive translation');
rejectsTranslation('Odległości: 250m, 800m i 1km.', 'Distances: [[ME0]]m and [[ME2]]km.', 'Protected value');
rejectsTranslation('Cena 900000 PLN', 'Price [[ME 0]] PLN', 'Protected value');
rejectsTranslation('Cena 900000 PLN', 'Price [[ME0]] and [[ME0]] PLN', 'Protected value');
rejectsTranslation('Cena 900000 PLN', 'Price [[ME0]] PLN and [[ME9]]', 'Unknown protected');
rejectsTranslation('Salon', 'Living room [[ME9]]', 'Unknown protected');
rejectsTranslation('Salon', 'Living room [[MEbogus]]', 'Unknown protected');
$splitRejected = false;
try {
    trValidateRuns(array_merge(translated('Price [[M'), translated('E0]] PLN, phone [[ME1]], area [[ME2]] m².')), $currentTokens, 100, $currentSource);
} catch (RuntimeException $error) { $splitRejected = str_contains($error->getMessage(), 'split'); }
ensure($splitRejected, 'protected token cannot be split between translation runs');

$uniform = trValidateRuns(translated('Living room'), [], 20, [['text' => 'Salon', 'strong' => true, 'em' => true]]);
ensure(!empty($uniform[0]['strong']) && !empty($uniform[0]['em']), 'uniform fragment emphasis restored deterministically');
$partialStyle = trValidateRuns(array_merge(translated('Living ', true), translated('room')), [], 20, [['text' => 'Salon', 'strong' => true]]);
ensure(!empty($partialStyle[0]['strong']) && !empty($partialStyle[1]['strong']), 'uniform source emphasis applies to every output run despite partial model marking');
$mixedRejected = false;
try {
    trValidateRuns(translated('Living room with balcony'), [], 30, [['text' => 'Salon', 'strong' => true], ['text' => ' z balkonem']]);
} catch (RuntimeException $error) { $mixedRejected = str_contains($error->getMessage(), 'emphasis'); }
ensure($mixedRejected, 'mixed source emphasis still must survive model translation');
$longRejected = false;
try { trValidateRuns(translated(str_repeat('Repeated context ', 60)), [], 3, [['text' => 'ENG']]); }
catch (RuntimeException $error) { $longRejected = str_contains($error->getMessage(), 'length'); }
ensure($longRejected, 'translation length guard remains in place');
foreach (['ENG', 'EN', 'PL', 'RU', 'UK', 'UA'] as $marker) {
    $markerRuns = [['text' => $marker, 'strong' => true]];
    ensure(trOpenAi([], 'en', ['runs' => $markerRuns], null) === $markerRuns, 'stand-alone language marker is preserved without model request: ' . $marker);
}
ensure(trLiteralLanguageMarker([['text' => 'ENG - mieszkanie']]) === null, 'marker inside real offer text is not treated as a literal-only fragment');
