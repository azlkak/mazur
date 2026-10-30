<?php
declare(strict_types=1);

require_once __DIR__ . '/offer-seo-lib.php';

const MAZUR_SEARCH_PAGE_SIZE = 10;
const MAZUR_SEARCH_TYPES = ['mieszkania', 'domy', 'dzialki', 'lokale'];

function mazurSearchCategory(string $type): ?string
{
    $name = mb_strtolower(trim($type), 'UTF-8');
    if (str_starts_with($name, 'działka') || str_contains($name, 'grunt')) return 'dzialki';
    if (str_starts_with($name, 'mieszkanie') || str_starts_with($name, 'apartament')) return 'mieszkania';
    if (str_starts_with($name, 'dom')) return 'domy';
    if (preg_match('/lokal|komerc|biuro|magazyn|hala|obiekt/u', $name)) return 'lokale';
    return null;
}

function mazurSearchSelect(iterable $rows, string $category, string $transaction, int $page, array $hiddenIds, array $hiddenProperties, array $prices): array
{
    $seen = [];
    $matching = [];
    foreach ($rows as $row) {
        $fields = json_decode((string)$row['fields_json'], true, 512, JSON_THROW_ON_ERROR);
        $source = (string)$row['source'];
        $id = (string)$row['source_id'];
        if (portalIsHidden($hiddenIds, $hiddenProperties, $source, $id, $fields)) continue;
        $propertyKey = portalPropertyKey($fields);
        if ($propertyKey !== '' && isset($seen[$propertyKey])) continue;
        if ($propertyKey !== '') $seen[$propertyKey] = true;
        if (mazurSearchCategory((string)($fields['typeName'] ?? '')) !== $category) continue;
        if ((string)($fields['transaction'] ?? '') !== ($transaction === 'wynajem' ? '132' : '131')) continue;
        $matching[] = [$row, $fields, count($matching)];
    }
    usort($matching, static fn(array $a, array $b): int =>
        strcmp((string)$b[0]['source_export_at'], (string)$a[0]['source_export_at'])
        ?: ($a[2] <=> $b[2]));
    $total = count($matching);
    $items = [];
    foreach (array_slice($matching, ($page - 1) * MAZUR_SEARCH_PAGE_SIZE, MAZUR_SEARCH_PAGE_SIZE) as [$row, $fields]) {
        $fields['price'] = portalOfferPrice($fields, $prices, (string)$row['source'], (string)$row['source_id']);
        $items[] = mazurSeoPublicOffer($row, $fields);
    }
    return ['items' => $items, 'total' => $total, 'pages' => max(1, (int)ceil($total / MAZUR_SEARCH_PAGE_SIZE))];
}

function mazurSearchLoad(PDO $db, string $category, string $transaction, int $page): array
{
    [$hiddenIds, $hiddenProperties] = portalHiddenSets($db);
    $prices = portalPriceOverrides($db);
    $hasSource = portalOffersHaveSource($db);
    $rows = $db->query($hasSource
        ? "SELECT source, source_id, source_export_at, fields_json, images_json FROM mls_offers WHERE publishable=1 ORDER BY (source='esticrm') DESC, source_export_at DESC, source_id DESC"
        : "SELECT 'mls' AS source, source_id, source_export_at, fields_json, images_json FROM mls_offers WHERE publishable=1 ORDER BY source_export_at DESC, source_id DESC");
    try {
        return mazurSearchSelect($rows, $category, $transaction, $page, $hiddenIds, $hiddenProperties, $prices);
    } finally {
        $rows->closeCursor();
    }
}

function mazurSearchEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mazurSearchCopy(string $lang): array
{
    return [
        'pl' => ['Mieszkania', 'Domy', 'Działki', 'Lokale komercyjne', 'na sprzedaż', 'na wynajem', 'Znaleziono {n} dopasowanych ofert', 'Poprzednia strona', 'Następna strona', 'Brak ofert spełniających wybrane kryteria.', 'Strona', 'Cena na zapytanie', 'pokoi', 'pomieszczenia', 'Działka', 'Parter', 'piętro', ' / mies.'],
        'en' => ['Apartments', 'Houses', 'Land', 'Commercial properties', 'for sale', 'for rent', '{n} matching properties found', 'Previous page', 'Next page', 'No properties match these filters.', 'Page', 'Ask for price', 'rooms', 'spaces', 'Plot', 'Ground floor', 'floor', ' / month'],
        'uk' => ['Квартири', 'Будинки', 'Земельні ділянки', 'Комерційні приміщення', 'на продаж', 'в оренду', 'Знайдено {n} відповідних пропозицій', 'Попередня сторінка', 'Наступна сторінка', 'Немає пропозицій за цими критеріями.', 'Сторінка', 'Ціна за запитом', 'кімнат', 'приміщення', 'Ділянка', 'Перший поверх', 'поверх', ' / міс.'],
        'ru' => ['Квартиры', 'Дома', 'Участки', 'Коммерческие помещения', 'на продажу', 'в аренду', 'Найдено {n} подходящих предложений', 'Предыдущая страница', 'Следующая страница', 'По этим критериям предложений нет.', 'Страница', 'Цена по запросу', 'комнат', 'помещения', 'Участок', 'Первый этаж', 'этаж', ' / мес.'],
    ][$lang];
}

function mazurSearchUrl(string $path, string $category, string $transaction, int $page): string
{
    return $path . '?type=' . $category . '&transaction=' . $transaction . ($page > 1 ? '&page=' . $page : '');
}

function mazurSearchCard(array $offer, string $category, string $transaction, string $lang): string
{
    $e = 'mazurSearchEscape';
    $t = mazurSearchCopy($lang);
    $url = '/oferta/?id=' . rawurlencode((string)$offer['id']);
    $title = (string)$offer['title'];
    $location = implode(', ', array_filter([(string)$offer['city'], (string)$offer['district']]));
    $price = (float)$offer['price'];
    $currency = (string)$offer['currency'] ?: 'PLN';
    $area = (float)$offer['area'];
    $image = (string)($offer['images'][0] ?? (['mieszkania'=>'category-apartments.webp', 'domy'=>'category-houses.webp', 'dzialki'=>'category-land.webp', 'lokale'=>'category-commercial.webp'][$category]));
    if (!$offer['images']) $image = '../assets/images/' . $image;
    $room = $category === 'dzialki' ? $t[14] : ($category === 'lokale' ? ((int)$offer['rooms'] ? $offer['rooms'] . ' ' . $t[13] : $t[13]) : ((int)$offer['rooms'] ? $offer['rooms'] . ' ' . $t[12] : ''));
    $floor = $category === 'dzialki' ? ((float)$offer['plotArea'] > 0 ? number_format((float)$offer['plotArea'], 0, ',', ' ') . ' m²' : '') : ($category === 'lokale' ? $t[15] : ((string)$offer['floor'] !== '' ? $offer['floor'] . ' ' . $t[16] : ''));
    $html = '<article class="offer-card" data-offer-card="' . $e((string)$offer['id']) . '">';
    $html .= '<div class="gallery"><img src="' . $e($image) . '" alt="' . $e($title) . '" loading="lazy" decoding="async" width="640" height="420"></div>';
    $html .= '<div class="offer-content"><div class="offer-top"><div><span class="price">' . $e($price > 0 ? number_format($price, 0, ',', ' ') . ' ' . $currency . ($transaction === 'wynajem' ? $t[17] : '') : $t[11]) . '</span>';
    if ($price > 0 && $area > 0) $html .= '<span class="unit-price">' . $e(number_format(round($price / $area), 0, ',', ' ') . ' ' . $currency . '/m²') . '</span>';
    $html .= '</div><button class="favorite" type="button" aria-label="Dodaj do ulubionych">♡</button></div>';
    $html .= '<h2 lang="pl"><a data-canonical-offer href="' . $e($url) . '">' . $e($title) . '</a></h2><p class="address">' . $e($location) . '</p><div class="details">';
    foreach ([$room, $area > 0 ? number_format($area, 1, ',', ' ') . ' m²' : '', $floor] as $detail) {
        if ($detail !== '') $html .= '<span class="detail"><i class="detail-icon">⌂</i>' . $e((string)$detail) . '</span>';
    }
    $html .= '</div><div class="offer-footer"><span class="offer-kind">' . $e($t[array_search($category, MAZUR_SEARCH_TYPES, true)] . ' ' . $t[$transaction === 'wynajem' ? 5 : 4]) . '</span></div></div></article>';
    return $html;
}

function mazurSearchPagination(string $path, string $category, string $transaction, int $page, int $pages, string $lang): string
{
    if ($pages <= 1) return '';
    $t = mazurSearchCopy($lang);
    $html = '';
    if ($page > 1) $html .= '<a class="page-button" rel="prev" aria-label="' . mazurSearchEscape($t[7]) . '" href="' . mazurSearchEscape(mazurSearchUrl($path, $category, $transaction, $page - 1)) . '">‹</a>';
    $numbers = array_unique(array_merge([1], range(max(1, $page - 1), min($pages, $page + 1)), [$pages]));
    $previous = 0;
    foreach ($numbers as $number) {
        if ($previous && $number > $previous + 1) $html .= '<span class="page-ellipsis" aria-hidden="true">…</span>';
        $html .= $number === $page
            ? '<span class="page-button active" aria-current="page">' . $number . '</span>'
            : '<a class="page-button" href="' . mazurSearchEscape(mazurSearchUrl($path, $category, $transaction, $number)) . '">' . $number . '</a>';
        $previous = $number;
    }
    if ($page < $pages) $html .= '<a class="page-button" rel="next" aria-label="' . mazurSearchEscape($t[8]) . '" href="' . mazurSearchEscape(mazurSearchUrl($path, $category, $transaction, $page + 1)) . '">›</a>';
    return $html;
}
