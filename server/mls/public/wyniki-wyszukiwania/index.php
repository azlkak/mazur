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
$categoryIntros = [
    'pl' => [
        'mieszkania' => [
            'sprzedaz' => 'Porównaj mieszkania pod kątem lokalizacji, metrażu, liczby pokoi i ceny. Użyj filtrów, by zawęzić wyniki do lokali odpowiadających Twoim potrzebom i budżetowi.',
            'wynajem' => 'Znajdź mieszkanie dopasowane do codziennego życia: sprawdź lokalizację, układ pomieszczeń i miesięczną cenę. Filtry pomogą Ci szybko porównać dostępne propozycje.',
        ],
        'domy' => [
            'sprzedaz' => 'Szukasz domu dla rodziny lub miejsca z przestrzenią do własnej aranżacji? Porównaj powierzchnię, położenie i cenę ofert, a szczegóły wybranej nieruchomości sprawdź na jej karcie.',
            'wynajem' => 'Przejrzyj domy dostępne na wynajem i wybierz lokalizację oraz metraż, które pasują do Twoich planów. Zawęź wyniki filtrami, by łatwiej porównać miesięczne koszty.',
        ],
        'dzialki' => [
            'sprzedaz' => 'Porównaj działki według lokalizacji, powierzchni i ceny. Jeśli planujesz budowę lub inwestycję, potraktuj ogłoszenie jako punkt wyjścia — możliwości wykorzystania gruntu wymagają osobnej weryfikacji.',
            'wynajem' => 'Szukasz terenu do wykorzystania bez jego zakupu? Sprawdź dostępne działki, ich powierzchnię i położenie, a przed podjęciem decyzji ustal dopuszczalny sposób użytkowania oraz warunki najmu.',
        ],
        'lokale' => [
            'sprzedaz' => 'Znajdź przestrzeń dla firmy lub inwestycji, porównując lokalizację, metraż i cenę. Przy wybranym lokalu warto dodatkowo sprawdzić, czy jego układ i możliwości techniczne pasują do planowanej działalności.',
            'wynajem' => 'Wybierz lokal dopasowany do sposobu prowadzenia biznesu — od położenia po potrzebną powierzchnię. Przed podpisaniem umowy sprawdź nie tylko czynsz, ale też warunki użytkowania i możliwości adaptacji.',
        ],
    ],
    'en' => [
        'mieszkania' => [
            'sprzedaz' => 'Compare apartments by location, floor area, number of rooms and price. Use the filters to narrow the results to homes that suit your needs and budget.',
            'wynajem' => 'Find an apartment that fits everyday life: check its location, layout and monthly rent. The filters make it easier to compare available homes.',
        ],
        'domy' => [
            'sprzedaz' => 'Looking for a family home or a place to make your own? Compare floor area, location and price, then explore the details on each property page.',
            'wynajem' => 'Browse houses for rent and choose the location and size that suit your plans. Use the filters to compare monthly costs more easily.',
        ],
        'dzialki' => [
            'sprzedaz' => 'Compare plots by location, size and price. If you plan to build or invest, treat the listing as a starting point: permitted uses of the land need separate verification.',
            'wynajem' => 'Need land without buying it? Check the available plots, their size and location, then confirm permitted uses and lease terms before deciding.',
        ],
        'lokale' => [
            'sprzedaz' => 'Find space for your business or investment by comparing location, floor area and price. Check whether the layout and technical possibilities suit your planned activity.',
            'wynajem' => 'Choose commercial space that fits how you work, from location to the area you need. Before signing, check the rent, terms of use and options for adaptation.',
        ],
    ],
    'uk' => [
        'mieszkania' => [
            'sprzedaz' => 'Порівняйте квартири за розташуванням, площею, кількістю кімнат і ціною. Скористайтеся фільтрами, щоб знайти варіанти відповідно до ваших потреб і бюджету.',
            'wynajem' => 'Знайдіть квартиру для повсякденного життя: оцініть розташування, планування та щомісячну орендну плату. Фільтри допоможуть порівняти доступні варіанти.',
        ],
        'domy' => [
            'sprzedaz' => 'Шукаєте будинок для родини або простір, який облаштуєте на власний смак? Порівняйте площу, розташування та ціну, а деталі перегляньте на сторінці об’єкта.',
            'wynajem' => 'Перегляньте будинки в оренду та оберіть розташування і площу, що відповідають вашим планам. Звузьте результати фільтрами, щоб легше порівняти щомісячні витрати.',
        ],
        'dzialki' => [
            'sprzedaz' => 'Порівняйте ділянки за розташуванням, площею та ціною. Якщо плануєте будівництво чи інвестицію, сприймайте оголошення як відправну точку: можливості використання землі слід перевірити окремо.',
            'wynajem' => 'Потрібна ділянка без купівлі? Перегляньте її площу та розташування, а перед рішенням з’ясуйте дозволене використання й умови оренди.',
        ],
        'lokale' => [
            'sprzedaz' => 'Знайдіть приміщення для бізнесу чи інвестиції, порівнявши розташування, площу та ціну. Додатково перевірте, чи відповідають планування й технічні можливості вашій діяльності.',
            'wynajem' => 'Оберіть приміщення відповідно до потреб бізнесу — від розташування до необхідної площі. Перед підписанням договору перевірте орендну плату, умови користування та можливості адаптації.',
        ],
    ],
    'ru' => [
        'mieszkania' => [
            'sprzedaz' => 'Сравните квартиры по расположению, площади, количеству комнат и цене. Используйте фильтры, чтобы найти варианты под ваши потребности и бюджет.',
            'wynajem' => 'Найдите квартиру для повседневной жизни: оцените расположение, планировку и ежемесячную аренду. Фильтры помогут сравнить доступные варианты.',
        ],
        'domy' => [
            'sprzedaz' => 'Ищете дом для семьи или место, которое можно обустроить по своему вкусу? Сравните площадь, расположение и цену, а подробности изучите на странице объекта.',
            'wynajem' => 'Посмотрите дома в аренду и выберите расположение и площадь под свои планы. Сузьте результаты фильтрами, чтобы легче сравнить ежемесячные расходы.',
        ],
        'dzialki' => [
            'sprzedaz' => 'Сравните участки по расположению, площади и цене. Если планируете строительство или инвестицию, считайте объявление отправной точкой: возможности использования земли нужно проверить отдельно.',
            'wynajem' => 'Нужен участок без покупки? Посмотрите его площадь и расположение, а перед решением уточните разрешённое использование и условия аренды.',
        ],
        'lokale' => [
            'sprzedaz' => 'Найдите помещение для бизнеса или инвестиции, сравнив расположение, площадь и цену. Дополнительно проверьте, подходят ли планировка и технические возможности для вашей деятельности.',
            'wynajem' => 'Выберите помещение под задачи бизнеса — от расположения до необходимой площади. Перед подписанием договора проверьте аренду, условия использования и возможности адаптации.',
        ],
    ],
];
$intro = $page === 1 && $status === 200 && $data['total'] > 0
    ? ($categoryIntros[$lang][$category][$transaction] ?? '')
    : '';
$canonical = 'https://mazurestate.pl' . mazurSearchUrl($path, $category, $transaction, $page);
$title = $heading . ($page > 1 ? ' — ' . $t[10] . ' ' . $page : '') . ' | MazurEstate';
$description = $intro !== '' ? $intro : $heading . '. ' . str_replace('{n}', (string)$data['total'], $t[6]) . ' — MazurEstate.';
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
if ($intro !== '') {
    $html = str_replace('<p id="results-summary"></p>', '<p class="category-intro">' . mazurSearchEscape($intro) . '</p><p id="results-summary"></p>', $html);
    $html = str_replace('styles.css?v=20261003-floor-dropdown', 'styles.css?v=20261103-category-intro', $html);
}
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
header('Cache-Control: ' . ($status === 200 ? 'public, max-age=60, must-revalidate' : 'no-store'));
if ($status !== 200 || $data['total'] === 0) header('X-Robots-Tag: noindex, follow');
if ($status === 503) header('Retry-After: 300');
echo $html;
