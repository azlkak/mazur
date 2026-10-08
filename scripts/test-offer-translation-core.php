<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/mls/public/api/description-formatter.php';
require_once __DIR__ . '/../server/mls/private/offer-translations.php';
require_once __DIR__ . '/../server/mls/private/bin/translate-offers.php';

function ensure(bool $okay, string $name): void
{
    if (!$okay) throw new RuntimeException('FAIL: ' . $name);
    echo 'PASS: ' . $name . PHP_EOL;
}

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
