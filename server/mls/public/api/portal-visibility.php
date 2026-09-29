<?php
declare(strict_types=1);

function portalPropertyKey(array $fields): string
{
    $company = trim((string)($fields['companyId'] ?? ''));
    $number = trim((string)($fields['number'] ?? ''));
    if ($company === '' || $number === '') return '';
    $key = $company . ':' . $number;
    return strlen($key) <= 160 ? $key : '';
}

function portalHiddenSets(PDO $db): array
{
    $ids = [];
    $properties = [];
    foreach ($db->query('SELECT source, source_id, property_key FROM portal_hidden_offers') as $row) {
        $ids[$row['source'] . ':' . $row['source_id']] = true;
        if ($row['property_key'] !== '') $properties[$row['property_key']] = true;
    }
    return [$ids, $properties];
}

function portalIsHidden(array $ids, array $properties, string $source, string $sourceId, array $fields): bool
{
    $key = portalPropertyKey($fields);
    return isset($ids[$source . ':' . $sourceId]) || ($key !== '' && isset($properties[$key]));
}
