<?php
declare(strict_types=1);
require_once __DIR__ . '/api/offer-seo-lib.php';

try {
    $xml = new XMLWriter();
    if (!$xml->openMemory()) throw new RuntimeException('Cannot open XML writer');
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('urlset');
    $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $count = 0;
    foreach (mazurSeoVisibleOfferUrls(mazurSeoDb()) as $item) {
        $xml->startElement('url');
        $xml->writeElement('loc', 'https://mazurestate.pl/oferta/?id=' . rawurlencode($item['id']));
        if ($item['lastmod'] !== '') {
            $modified = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $item['lastmod'], new DateTimeZone('UTC'));
            if ($modified !== false) $xml->writeElement('lastmod', $modified->format('Y-m-d'));
        }
        $xml->endElement();
        $count++;
        if ($count > 50000) throw new RuntimeException('Sitemap exceeds 50,000 URLs; split required');
    }
    $xml->endElement();
    $xml->endDocument();
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo $xml->outputMemory();
} catch (Throwable $error) {
    error_log('Mazur offer sitemap unavailable: ' . get_class($error));
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('Retry-After: 300');
    echo 'Offer sitemap temporarily unavailable';
}
