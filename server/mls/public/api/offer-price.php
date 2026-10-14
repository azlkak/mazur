<?php
declare(strict_types=1);

// Imported prices stay untouched. A manual portal price survives later batches.
function portalPriceOverrides(PDO $db): array
{
    $prices = [];
    try {
        $rows = $db->query('SELECT source, source_id, price FROM portal_offer_prices');
    } catch (PDOException $error) {
        // During rollout, the old public catalogue must remain available.
        if ($error->getCode() === '42S02') return [];
        throw $error;
    }
    foreach ($rows as $row) {
        $prices[$row['source'] . ':' . $row['source_id']] = (float)$row['price'];
    }
    return $prices;
}

function portalEnsurePriceTable(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS portal_offer_prices (
        source VARCHAR(16) CHARACTER SET ascii NOT NULL,
        source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
        price DECIMAL(15,2) NOT NULL,
        updated_by VARCHAR(254) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (source, source_id)
    ) ENGINE=InnoDB");
}

function portalOfferPrice(array $fields, array $prices, string $source, string $sourceId): float
{
    return $prices[$source . ':' . $sourceId] ?? (float)($fields['price'] ?? 0);
}
