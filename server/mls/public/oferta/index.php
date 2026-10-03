<?php
declare(strict_types=1);

// Deploy beside the existing oferta/index.html. Git remains the source of truth;
// Hostinger executes this file so crawlers receive the actual offer in HTML.
require_once __DIR__ . '/../api/offer-seo-lib.php';

function offerHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function offerReplace(string $html, string $needle, string $replacement): string
{
    $position = strpos($html, $needle);
    if ($position === false) throw new RuntimeException('Offer template changed');
    return substr_replace($html, $replacement, $position, strlen($needle));
}

function offerNumber(float $value, int $decimals = 0): string
{
    return number_format($value, $decimals, ',', ' ');
}

function offerRender(array $offer, string $template): string
{
    $id = (string)$offer['id'];
    $canonical = 'https://mazurestate.pl/oferta/?id=' . rawurlencode($id);
    $title = (string)$offer['title'];
    $city = (string)$offer['city'];
    $district = (string)$offer['district'];
    $province = (string)$offer['province'];
    $location = implode(', ', array_filter([$city, $district], static fn($v): bool => $v !== ''));
    $locationFull = $location . ($province !== '' ? ' · ' . $province : '');
    $description = trim((string)$offer['description']);
    $summary = $title . ($location !== '' ? ' — ' . $location : '') . '. Sprawdź cenę, zdjęcia i szczegóły oferty MazurEstate.';
    $transaction = $offer['transaction'] === 'wynajem' ? 'na wynajem' : 'na sprzedaż';
    $price = (float)$offer['price'] > 0
        ? offerNumber((float)$offer['price']) . ' ' . ((string)$offer['currency'] ?: 'PLN')
        : 'Cena na zapytanie';
    $area = (float)$offer['area'];
    $unit = $area > 0 && (float)$offer['price'] > 0
        ? offerNumber(round((float)$offer['price'] / $area)) . ' ' . ((string)$offer['currency'] ?: 'PLN') . '/m²'
        : '';
    $images = $offer['images'];
    $mainImage = (string)($images[0] ?? '');
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $canonical . '#webpage',
        'url' => $canonical,
        'name' => $title . ' | MazurEstate',
        'description' => $summary,
        'inLanguage' => 'pl',
        'isPartOf' => ['@id' => 'https://mazurestate.pl/#website'],
    ];
    if ($mainImage !== '') $schema['primaryImageOfPage'] = $mainImage;
    $seo = '<!-- SEO START -->' . "\n"
        . '<title>' . offerHtml($title) . ' | MazurEstate</title>' . "\n"
        . '<meta name="description" content="' . offerHtml($summary) . '">' . "\n"
        . '<meta name="robots" content="index,follow,max-image-preview:large">' . "\n"
        . '<meta name="mazur-offer-ssr" content="1">' . "\n"
        . '<link rel="canonical" href="' . offerHtml($canonical) . '">' . "\n"
        . '<meta property="og:type" content="article">' . "\n"
        . '<meta property="og:title" content="' . offerHtml($title) . ' | MazurEstate">' . "\n"
        . '<meta property="og:description" content="' . offerHtml($summary) . '">' . "\n"
        . '<meta property="og:url" content="' . offerHtml($canonical) . '">' . "\n"
        . ($mainImage !== '' ? '<meta property="og:image" content="' . offerHtml($mainImage) . '">' . "\n" : '')
        . '<script type="application/ld+json">'
        . json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
        . '</script>' . "\n"
        . '<script src="../assets/js/language-routing.js"></script>' . "\n"
        . '<script src="../assets/js/seo-config.js"></script>' . "\n"
        . '<script src="../assets/js/seo.js"></script>' . "\n"
        . '<!-- SEO END -->';
    $count = 0;
    $template = preg_replace_callback('/<!-- SEO START -->.*?<!-- SEO END -->/s', static fn(): string => $seo, $template, 1, $count);
    if ($count !== 1 || $template === null) throw new RuntimeException('Offer SEO template changed');

    $template = offerReplace($template, '<strong>Oferta</strong>', '<strong>' . offerHtml($location ?: $title) . '</strong>');
    $template = offerReplace($template, '<span class="eyebrow">OFERTA</span><h1>Wczytywanie oferty…</h1><p class="location"></p>',
        '<span class="eyebrow">' . offerHtml(mb_strtoupper((string)$offer['type'] . ' ' . $transaction, 'UTF-8')) . '</span>'
        . '<h1>' . offerHtml($title) . '</h1><p class="location">' . offerHtml($locationFull) . '</p>');
    $template = offerReplace($template, '<div class="head-price" hidden><strong></strong><span></span></div>',
        '<div class="head-price"><strong>' . offerHtml($price) . '</strong><span>'
        . offerHtml(trim(($offer['transaction'] === 'wynajem' ? 'miesięcznie' : '') . ($unit !== '' ? ' · ' . $unit : ''), ' ·'))
        . '</span></div>');

    $galleryImages = $images !== [] ? $images : ['../assets/images/category-apartments.webp'];
    $gallery = '<section class="gallery" id="gallery">';
    foreach (array_slice($galleryImages, 0, 5) as $index => $src) {
        $class = $index === 0 ? ' class="gallery-main"' : ($index === 4 && count($galleryImages) > 5 ? ' class="gallery-more"' : '');
        $gallery .= '<button' . $class . ' type="button"><img src="' . offerHtml((string)$src)
            . '" alt="' . offerHtml($title . ' — zdjęcie ' . ($index + 1))
            . '" loading="' . ($index === 0 ? 'eager' : 'lazy') . '" decoding="async">';
        if ($index === 4 && count($galleryImages) > 5) {
            $gallery .= '<span>Zobacz wszystkie zdjęcia · ' . count($galleryImages) . '</span>';
        }
        $gallery .= '</button>';
    }
    $gallery .= '</section>';
    $template = offerReplace($template, '<section class="gallery" id="gallery" hidden></section>', $gallery);
    $template = offerReplace($template, '<div class="property-layout" hidden>', '<div class="property-layout">');

    $kind = (string)$offer['type'];
    $pairs = [
        ['Powierzchnia', $area > 0 ? offerNumber($area, fmod($area, 1.0) === 0.0 ? 0 : 2) . ' m²' : '—'],
        [$kind === 'Działka' ? 'Powierzchnia działki' : 'Liczba pokoi',
            $kind === 'Działka' ? ((float)$offer['plotArea'] > 0 ? offerNumber((float)$offer['plotArea']) . ' m²' : '—') : ((int)$offer['rooms'] > 0 ? (string)$offer['rooms'] : '—')],
        ['Piętro', (string)$offer['floor'] !== '' ? (string)$offer['floor'] . ((string)$offer['buildingFloors'] !== '' ? ' / ' . $offer['buildingFloors'] : '') : '—'],
        ['Typ nieruchomości', $kind ?: '—'],
        ['Rok budowy', (string)$offer['buildingYear'] ?: '—'],
        ['Numer oferty', (string)$offer['number'] ?: $id],
    ];
    $parameters = '<section class="parameter-grid">';
    foreach ($pairs as [$label, $value]) {
        $parameters .= '<article><span>' . offerHtml($label) . '</span><strong>' . offerHtml($value) . '</strong></article>';
    }
    $parameters .= '</section>';
    $count = 0;
    $template = preg_replace_callback('/<section class="parameter-grid">.*?<\/section>/s', static fn(): string => $parameters, $template, 1, $count);
    if ($count !== 1 || $template === null) throw new RuntimeException('Offer parameter template changed');

    $body = '<section class="description"><p class="eyebrow">O NIERUCHOMOŚCI</p><h2>' . offerHtml($title) . '</h2>';
    $paragraphs = preg_split('/\n+/u', $description !== '' ? $description : 'Skontaktuj się z nami, aby poznać szczegóły tej nieruchomości.');
    foreach ($paragraphs ?: [] as $paragraph) {
        if (trim($paragraph) !== '') $body .= '<p>' . offerHtml(trim($paragraph)) . '</p>';
    }
    $body .= '</section>';
    $template = offerReplace($template, '<section class="description"></section>', $body);

    $features = $offer['features'] ?: ['Szczegóły dostępne u doradcy'];
    $featureHtml = '';
    foreach (array_slice($features, 0, 12) as $feature) $featureHtml .= '<span>✓ ' . offerHtml((string)$feature) . '</span>';
    $template = offerReplace($template,
        '<section class="features"><p class="eyebrow">UDOGODNIENIA</p><h2>Najważniejsze atuty</h2><div></div></section>',
        '<section class="features"><p class="eyebrow">UDOGODNIENIA</p><h2>Najważniejsze atuty</h2><div>' . $featureHtml . '</div></section>');

    $seed = json_encode(['offer' => $offer], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    // The legacy detail script runs synchronously near the end of the page.
    // Make the payload available before that script executes.
    return offerReplace($template, '<script src="script.js',
        '<script id="server-offer" type="application/json">' . $seed . '</script><script src="script.js');
}

$template = __DIR__ . '/index.html';
$id = (string)($_GET['id'] ?? '');
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, must-revalidate');
if ($id === '' && is_file($template)) {
    // Generic route is deliberately noindex; only real, visible IDs are indexable.
    readfile($template);
    exit;
}
if (!preg_match('/\A(?:esti-)?[0-9]{1,40}\z/', $id)) {
    http_response_code(404);
    header('Cache-Control: no-store');
    if (is_file($template)) readfile($template);
    exit;
}
try {
    $offer = mazurSeoFindOffer(mazurSeoDb(), $id);
    if ($offer === null) {
        http_response_code(404);
        header('Cache-Control: no-store');
        if (is_file($template)) readfile($template);
        exit;
    }
    if (!is_file($template)) throw new RuntimeException('Offer template missing');
    echo offerRender($offer, (string)file_get_contents($template));
} catch (Throwable $error) {
    error_log('Mazur offer SSR unavailable: ' . get_class($error));
    http_response_code(503);
    header('Retry-After: 300');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="pl"><meta charset="utf-8"><title>Oferta chwilowo niedostępna</title><h1>Oferta chwilowo niedostępna</h1><p>Spróbuj ponownie za kilka minut.</p></html>';
}
