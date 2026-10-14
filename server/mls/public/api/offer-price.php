<?php
declare(strict_types=1);

// Imported prices stay untouched. A manual portal price survives later batches.
function portalPriceOverrides(PDO $db): array
{
    $prices = [];
    foreach ($db->query('SELECT source, source_id, price FROM portal_offer_prices') as $row) {
        $prices[$row['source'] . ':' . $row['source_id']] = (float)$row['price'];
    }
    return $prices;
}

function portalOfferPrice(array $fields, array $prices, string $source, string $sourceId): float
{
    return $prices[$source . ':' . $sourceId] ?? (float)($fields['price'] ?? 0);
}
