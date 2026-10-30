<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/search-results-seo.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($requestPath) || !preg_match('~^/(?:(en|uk|ru)/)?wyniki-wyszukiwania/(?:index\.html)?$~D', $requestPath, $match)) {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    exit('Not found');
}
$lang = $match[1] ?: 'pl';
$path = ($lang === 'pl' ? '/' : '/' . $lang . '/') . 'wyniki-wyszukiwania/';
$query = $_GET;
$oldLang = $query['lang'] ?? null;
if (is_string($oldLang) && in_array($oldLang, ['pl', 'en', 'uk', 'ru'], true)) {
    unset($query['lang']);
    header('Location: ' . ($oldLang === 'pl' ? '/' : '/' . $oldLang . '/') . 'wyniki-wyszukiwania/' . ($query ? '?' . http_build_query($query) : ''), true, 301);
    exit;
}
if (str_ends_with($requestPath, 'index.html')) {
    header('Location: ' . $path . ($query ? '?' . http_build_query($query) : ''), true, 301);
    exit;
}

$category = $query['type'] ?? null;
$transaction = $query['transaction'] ?? null;
$pageRaw = $query['page'] ?? '1';
$page = is_string($pageRaw) && preg_match('/^[1-9][0-9]{0,4}$/D', $pageRaw) ? (int)$pageRaw : 0;
$isCategory = is_string($category) && in_array($category, MAZUR_SEARCH_TYPES, true)
    && is_string($transaction) && in_array($transaction, ['sprzedaz', 'wynajem'], true)
    && $page > 0 && count(array_diff(array_keys($query), ['type', 'transaction', 'page'])) === 0;

if ($isCategory && array_key_exists('page', $query) && $page === 1) {
    header('Location: ' . mazurSearchUrl($path, $category, $transaction, 1), true, 301);
    exit;
}

$templatePath = __DIR__ . '/../' . ($lang === 'pl' ? '' : $lang . '/') . 'wyniki-wyszukiwania/index.html';
$html = file_get_contents($templatePath);
if ($html === false) {
    http_response_code(503);
    header('X-Robots-Tag: noindex');
    exit('Search template unavailable');
}
header('Content-Type: text/html; charset=utf-8');
if (!$isCategory) {
    header('Cache-Control: no-store');
    echo $html;
    exit;
}

$status = 200;
$data = ['items' => [], 'total' => 0, 'pages' => 1];
try {
    $cacheKey = 'mazur-search-v1-' . $category . '-' . $transaction . '-' . $page;
    $cached = function_exists('apcu_fetch') ? apcu_fetch($cacheKey) : false;
    $data = is_array($cached) ? $cached : mazurSearchLoad(mazurSeoDb(), $category, $transaction, $page);
    if (!is_array($cached) && function_exists('apcu_store')) apcu_store($cacheKey, $data, 60);
    if ($page > $data['pages']) $status = 404;
} catch (Throwable $error) {
    error_log('Mazur search SSR unavailable: ' . get_class($error));
    $status = 503;
}

$t = mazurSearchCopy($lang);
$typeName = $t[array_search($category, MAZUR_SEARCH_TYPES, true)];
$heading = $typeName . ' ' . $t[$transaction === 'wynajem' ? 5 : 4];
$canonical = 'https://mazurestate.pl' . mazurSearchUrl($path, $category, $transaction, $page);
$title = $heading . ($page > 1 ? ' — ' . $t[10] . ' ' . $page : '') . ' | MazurEstate';
$description = $heading . '. ' . str_replace('{n}', (string)$data['total'], $t[6]) . ' — MazurEstate.';
$seo = '<!-- SEO START -->' . "\n"
    . '<title>' . mazurSearchEscape($title) . '</title>' . "\n"
    . '<meta name="description" content="' . mazurSearchEscape($description) . '">' . "\n"
    . '<meta name="robots" content="' . ($status === 200 && $data['total'] > 0 ? 'index,follow,max-image-preview:large' : 'noindex,follow') . '">' . "\n"
    . '<meta name="mazur-search-ssr" content="1">' . "\n"
    . '<link rel="canonical" href="' . mazurSearchEscape($canonical) . '">' . "\n";
if ($status === 200 && $data['total'] > 0) {
    foreach (['pl', 'en', 'uk', 'ru', 'x-default'] as $alternate) {
        $alternatePath = ($alternate === 'pl' || $alternate === 'x-default' ? '/' : '/' . $alternate . '/') . 'wyniki-wyszukiwania/';
        $seo .= '<link rel="alternate" hreflang="' . $alternate . '" href="https://mazurestate.pl' . mazurSearchEscape(mazurSearchUrl($alternatePath, $category, $transaction, $page)) . '">' . "\n";
    }
}
$seo .= '<script src="../assets/js/language-routing.js?v=20261030-search-ssr"></script>' . "\n"
    . '<script src="../assets/js/seo-config.js"></script>' . "\n"
    . '<script src="../assets/js/seo.js?v=20261030-search-ssr"></script>' . "\n"
    . '<!-- SEO END -->';
$html = preg_replace('~<!-- SEO START -->.*?<!-- SEO END -->~s', $seo, $html, 1);
if ($html === null) throw new RuntimeException('Search SEO template changed');
$html = str_replace('<h1 id="results-title">Nieruchomości na sprzedaż</h1>', '<h1 id="results-title">' . mazurSearchEscape($heading) . '</h1>', $html);
$summary = $status === 503 ? 'Oferty są chwilowo niedostępne.' : ($status === 404 ? 'Ta strona wyników nie istnieje.' : str_replace('{n}', (string)$data['total'], $t[6]));
$html = str_replace('<p id="results-summary"></p>', '<p id="results-summary">' . mazurSearchEscape($summary) . '</p>', $html);
$cards = '';
if ($status === 200) {
    foreach ($data['items'] as $offer) $cards .= mazurSearchCard($offer, $category, $transaction, $lang);
    if ($cards === '') $cards = '<p>' . mazurSearchEscape($t[9]) . '</p>';
}
$html = str_replace('<section id="offer-list" class="offer-list" aria-live="polite"></section>', '<section id="offer-list" class="offer-list" aria-live="polite">' . $cards . '</section>', $html);
$pagination = $status === 200 ? mazurSearchPagination($path, $category, $transaction, $page, $data['pages'], $lang) : '';
$html = str_replace('<nav id="pagination" class="pagination"></nav>', '<nav id="pagination" class="pagination">' . $pagination . '</nav>', $html);
http_response_code($status);
header('Cache-Control: public, max-age=60, must-revalidate');
if ($status !== 200 || $data['total'] === 0) header('X-Robots-Tag: noindex, follow');
if ($status === 503) header('Retry-After: 300');
echo $html;
