<?php
declare(strict_types=1);

// Public IDs retain the existing MLS and EstiCRM formats.
function portalParseOfferId(string $id): ?array
{
    if (preg_match('/\A[0-9]{1,40}\z/', $id)) return ['mls', $id];
    if (preg_match('/\Aesti-([0-9]{1,40})\z/', $id, $match)) return ['esticrm', $match[1]];
    if (preg_match('/\Avix-(O-[0-9]{1,36})\z/', $id, $match)) return ['vixcrm', $match[1]];
    return null;
}

function portalPublicOfferId(string $source, string $sourceId): string
{
    return match ($source) {
        'esticrm' => 'esti-' . $sourceId,
        'vixcrm' => 'vix-' . $sourceId,
        default => $sourceId,
    };
}

function portalImageUrl(string $image): string
{
    // VixCRM URLs are accepted only from the expected photo path.
    if (preg_match('~\Ahttps://vixcrm\.com/photo/e/[0-9/]+/photo\.png\z~', $image)) return $image;
    return 'https://api.mazurestate.pl/api/mls-image.php?name=' . rawurlencode($image);
}
