<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/mls/public/api/search-results-seo.php';

function searchFixture(string $id, string $source = 'mls', array $fields = [], string $date = '2026-10-30 10:00:00'): array
{
    return ['source'=>$source, 'source_id'=>$id, 'source_export_at'=>$date,
        'fields_json'=>json_encode($fields + ['typeName'=>'Dom', 'portalTitle'=>'Dom testowy', 'price'=>700000, 'priceCurrency'=>'2', 'transaction'=>'131', 'areaTotal'=>120]),
        'images_json'=>'[]'];
}
function expectSearch(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}

$rows = [
    searchFixture('215181', 'esticrm'),
    searchFixture('20', 'esticrm', ['companyId'=>'1', 'number'=>'same']),
    searchFixture('21', 'mls', ['companyId'=>'1', 'number'=>'same']),
    searchFixture('22', 'mls', ['companyId'=>'1', 'number'=>'hidden']),
    searchFixture('23', 'mls', ['typeName'=>'Mieszkanie']),
    searchFixture('24', 'mls', ['transaction'=>'132']),
];
for ($i = 100; $i < 115; $i++) $rows[] = searchFixture((string)$i, 'mls', [], '2026-10-' . sprintf('%02d', $i - 84) . ' 10:00:00');
$hiddenIds = ['esticrm:215181'=>true];
$hiddenProperties = ['1:hidden'=>true];
$prices = ['esticrm:20'=>123456];
$first = mazurSearchSelect($rows, 'domy', 'sprzedaz', 1, $hiddenIds, $hiddenProperties, $prices);
$second = mazurSearchSelect($rows, 'domy', 'sprzedaz', 2, $hiddenIds, $hiddenProperties, $prices);
expectSearch($first['total'] === 16 && $first['pages'] === 2, 'Hidden, duplicate, other category and rental rows excluded');
expectSearch(count($first['items']) === 10 && count($second['items']) === 6, 'Ten offers per page');
expectSearch(count(array_unique(array_column(array_merge($first['items'], $second['items']), 'id'))) === 16, 'No missing or repeated offers');
$selectedEsti = array_values(array_filter(array_merge($first['items'], $second['items']), static fn(array $item): bool => $item['id'] === 'esti-20'));
expectSearch(count($selectedEsti) === 1 && $selectedEsti[0]['price'] === 123456.0, 'EstiCRM chosen over MLS duplicate and price override used');
expectSearch(mazurSearchCategory('DZIAŁKA budowlana') === 'dzialki' && mazurSearchCategory('Apartament') === 'mieszkania' && mazurSearchCategory('Hala magazynowa') === 'lokale', 'Category mapping matches search');
$card = mazurSearchCard($selectedEsti[0], 'domy', 'sprzedaz', 'pl');
expectSearch(str_contains($card, 'href="/oferta/?id=esti-20"') && str_contains($card, '123 456 PLN'), 'Existing card style gets canonical offer link and override price');
$danger = mazurSearchSelect([searchFixture('9', 'mls', ['portalTitle'=>'<script>alert(1)</script>'])], 'domy', 'sprzedaz', 1, [], [], []);
expectSearch(str_contains(mazurSearchCard($danger['items'][0], 'domy', 'sprzedaz', 'pl'), '&lt;script&gt;'), 'CRM text is escaped');
$pagination = mazurSearchPagination('/domy/', 'domy', 'sprzedaz', 2, 8, 'pl');
expectSearch(str_contains($pagination, 'href="/domy/?type=domy&amp;transaction=sprzedaz"') && str_contains($pagination, 'rel="prev"'), 'Pagination uses crawlable links');
