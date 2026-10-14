<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/mls/private/esticrm-offer-link.php';

$fields = [
    'numberExport' => 'O-1', 'portalTitle' => 'Mieszkanie w Warszawie',
    'mainTypeId' => '2', 'transaction' => '131', 'price' => 690000,
    'priceCurrency' => '2', 'areaTotal' => 51.5,
    'locationCityName' => 'Warszawa', 'descriptionWebsite' => 'Opis',
];
$payload = mazurEstiOfferPayload($fields, 'vixcrm', 'O-1', 254, 'inwest.warszawa@gmail.com');
if ($payload['user_id'] !== 254 || $payload['estate'] !== 2
    || $payload['transaction'] !== 131 || $payload['price'] !== 690000.0
    || $payload['area_total'] !== 51.5 || $payload['price_currency'] !== 2
    || $payload['email'] !== 'inwest.warszawa@gmail.com') {
    throw new RuntimeException('Property mapping failed');
}
if (array_key_exists('location_country_id', $payload)) {
    throw new RuntimeException('Unknown country was fabricated');
}
foreach (['firstname', 'lastname', 'phone'] as $ownerField) {
    if (array_key_exists($ownerField, $payload)) throw new RuntimeException('Enquirer assigned as property owner');
}
try {
    mazurEstiOfferPayload($fields, 'vixcrm', 'O-1', 254, '');
    throw new RuntimeException('Missing agency contact was accepted');
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'EstiCRM agency contact email is missing') throw $error;
}
if (!str_contains($payload['notice'], 'vix-O-1') || !str_contains($payload['notice'], 'Warszawa')) {
    throw new RuntimeException('Source context missing');
}

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $db = new PDO('sqlite::memory:');
    $db->sqliteCreateFunction('GET_LOCK', static fn($name, $seconds): int => 1, 2);
    $db->sqliteCreateFunction('RELEASE_LOCK', static fn($name): int => 1, 1);
    $db->exec('CREATE TABLE esticrm_offer_links (source TEXT, source_id TEXT, state TEXT, esticrm_id INTEGER, esticrm_number TEXT, PRIMARY KEY(source,source_id))');
    $db->exec("INSERT INTO esticrm_offer_links VALUES ('vixcrm','O-1','linked',12345,'EST-12345')");
    $config = ['esticrm_offer_creation_enabled' => true];
    if (mazurEstiLinkExternalOffer($db, $config, 'vixcrm', 'O-1', $fields) !== 'EST-12345') {
        throw new RuntimeException('Existing link was not reused');
    }
    $db->exec("INSERT INTO esticrm_offer_links VALUES ('vixcrm','O-2','review',NULL,'')");
    if (mazurEstiLinkExternalOffer($db, $config, 'vixcrm', 'O-2', $fields) !== '') {
        throw new RuntimeException('Uncertain creation was retried');
    }
}
echo "EstiCRM offer payload and existing links: passed\n";
